<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

function chatgpt_handle_batch_action($action, $collection)
{
  global $page;

  if ($action !== 'chatgpt_caption') {
    return;
  }

  // Process images in batches of 5
  $processed = 0;
  $errors = 0;
  $error_messages = array();
  $batch_size = 5; // Process 5 images at a time
  
  // Process collection in batches
  $batches = array_chunk($collection, $batch_size);
  
  foreach ($batches as $batch) {
    $image_data = array();
    $valid_images = array();
    
    // Prepare image data for this batch
    foreach ($batch as $image_id) {
      // Get the image path
      $query = "
          SELECT path 
          FROM " . IMAGES_TABLE . " 
          WHERE id = " . $image_id;
      $result = pwg_query($query);
      $image = pwg_db_fetch_assoc($result);

      if (!$image) {
        $errors++;
        continue;
      }

      $current_comment = isset($image['comment']) ? $image['comment'] : '';
      // Check if the image already has an AI caption
      if (strpos($current_comment, 'AI Caption:') !== false) {
        $errors++;
        $error_messages[] = "Image #$image_id: Already captioned.";
        continue;
      }
      // Get full path to original image
      $image_path = chatgpt_get_image_path($image);

      if (!file_exists($image_path)) {
        $errors++;
        $error_messages[] = "Image #$image_id: File not found at $image_path";
        continue;
      }
      
      // Store valid image data for processing
      $valid_images[] = array(
        'id' => $image_id,
        'path' => $image_path
      );
    }
    
    // Process valid images in parallel
    if (!empty($valid_images)) {
      $results = chatgpt_process_images_parallel($valid_images);
      
      // Process results
      foreach ($results as $result) {
        if (isset($result['error'])) {
          $errors++;
          $error_messages[] = "Image #{$result['id']}: {$result['error']}";
        } else {
          // Update description
          chatgpt_update_description($result['id'], $result['caption']);
          $processed++;
        }
      }
    }
  }

  // Show success/error messages
  if ($processed > 0) {
    $page['infos'][] = "Successfully generated captions for $processed images.";
  }

  if ($errors > 0) {
    $page['errors'][] = "Failed to process $errors images.";
    foreach ($error_messages as $error) {
      $page['errors'][] = $error;
    }
  }

  // Return true to indicate that we've handled the action
  return true;
}

// Process multiple images in parallel using multi-curl
function chatgpt_process_images_parallel($images) {
  global $conf;
  
  // Get API key and model from configuration
  if (!isset($conf['chatgpt_captioner'])) {
    return array_map(function($img) {
      return array('id' => $img['id'], 'error' => 'Gemini configuration not found');
    }, $images);
  }
  
  $chatgpt_conf = unserialize($conf['chatgpt_captioner']);
  
  // Validate configuration
  if (empty($chatgpt_conf['api_key']) || $chatgpt_conf['api_key'] == 'your-gemini-api-key') {
    return array_map(function($img) {
      return array('id' => $img['id'], 'error' => 'API key not configured');
    }, $images);
  }
  
  $api_key = $chatgpt_conf['api_key'];
  $model = $chatgpt_conf['model'];
  $user_prompt = $chatgpt_conf['user_prompt'];
  
  // Prepare multi-curl
  $mh = curl_multi_init();
  $curl_handles = array();
  $results = array();
  
  // Initialize each request
  foreach ($images as $index => $image) {
    $image_id = $image['id'];
    $image_path = $image['path'];
    
    // Resize image if needed to meet API requirements
    $resized_image_path = chatgpt_resize_image($image_path);
    if ($resized_image_path === false) {
      // Get file extension to provide more specific error message
      $file_extension = strtolower(pathinfo($image_path, PATHINFO_EXTENSION));
      if (!in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])) {
        $results[$index] = array(
          'id' => $image_id,
          'error' => 'Unsupported image format. Only JPG, PNG, and GIF formats are supported.'
        );
      } else {
        $results[$index] = array(
          'id' => $image_id,
          'error' => 'Could not process image for API submission. The file may be corrupted or in an invalid format.'
        );
      }
      continue;
    }
    
    // Base64 encode the image
    $image_data = base64_encode(file_get_contents($resized_image_path));
    
    // Clean up temp file if we created one
    if ($resized_image_path !== $image_path) {
      unlink($resized_image_path);
    }
    
    // Prepare the API request
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $api_key;
    $headers = [
      'Content-Type: application/json'
    ];
    
    $data = [
      'contents' => [
        [
          'parts' => [
            [
              'text' => $user_prompt
            ],
            [
              'inline_data' => [
                'mime_type' => 'image/jpeg',
                'data' => $image_data
              ]
            ]
          ]
        ]
      ],
      'generation_config' => [
        'max_output_tokens' => 300,
        'temperature' => 0.4
      ]
    ];
    
    // Initialize cURL session
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    // Store image ID with the handle for later reference
    $curl_handles[$index] = array(
      'handle' => $ch,
      'id' => $image_id
    );
    
    // Add the handle to the multi-curl
    curl_multi_add_handle($mh, $ch);
  }
  
  // Execute the multi-curl handles
  $running = null;
  do {
    curl_multi_exec($mh, $running);
    curl_multi_select($mh); // Prevents CPU hogging
  } while ($running > 0);
  
  // Process the results
  foreach ($curl_handles as $index => $info) {
    // Skip if we already have an error for this image
    if (isset($results[$index])) {
      continue;
    }
    
    $ch = $info['handle'];
    $image_id = $info['id'];
    
    $response = curl_multi_getcontent($ch);
    $err = curl_error($ch);
    
    if ($err) {
      $results[$index] = array(
        'id' => $image_id,
        'error' => $err
      );
    } else {
      $response_data = json_decode($response, true);
      
      // Check for successful response
      if (isset($response_data['candidates'][0]['content']['parts'][0]['text'])) {
        $results[$index] = array(
          'id' => $image_id,
          'caption' => trim($response_data['candidates'][0]['content']['parts'][0]['text'])
        );
      } 
      // Check for errors in the response
      elseif (isset($response_data['error'])) {
        $results[$index] = array(
          'id' => $image_id,
          'error' => 'API Error: ' . $response_data['error']['message']
        );
      } else {
        $results[$index] = array(
          'id' => $image_id,
          'error' => 'Unexpected API response format.'
        );
      }
    }
    
    // Remove the handle
    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);
  }
  
  // Close the multi-curl handle
  curl_multi_close($mh);
  
  return $results;
}

// Helper function to get the file path for an image
function chatgpt_get_image_path($element)
{
  if (!isset($element['path'])) {
    return false;
  }

  // Use Piwigo's built-in function if available
  if (function_exists('get_element_path')) {
    return get_element_path($element);
  }

  // Try different approaches to find the correct galleries path
  if (defined('PHPWG_ROOT_PATH') && defined('GALLERIES_PATH')) {
    return PHPWG_ROOT_PATH . GALLERIES_PATH . $element['path'];
  }

  if (function_exists('get_gallery_home_dir')) {
    return get_gallery_home_dir() . $element['path'];
  }

  // Fallback to common path
  return PHPWG_ROOT_PATH . 'galleries/' . $element['path'];
}


// Function to generate caption for an image using Gemini API
// Function to generate caption for an image using Gemini API
function chatgpt_generate_caption($image_path)
{
  global $conf;

  // Get API key and model from configuration
  if (!isset($conf['chatgpt_captioner'])) {
    return "Error: Gemini configuration not found";
  }
  // Get API key and model from configuration
  $chatgpt_conf = unserialize($conf['chatgpt_captioner']);

  // Validate configuration
  if (empty($chatgpt_conf['api_key']) || $chatgpt_conf['api_key'] == 'your-gemini-api-key') {
    return "Error: API key not configured";
  }

  $api_key = $chatgpt_conf['api_key'];
  $model = $chatgpt_conf['model'];
  $user_prompt = $chatgpt_conf['user_prompt'];

  // Check if file exists
  if (!file_exists($image_path)) {
    return "Error: Image file not found at $image_path";
  }

  // Resize image if needed to meet API requirements
  $resized_image_path = chatgpt_resize_image($image_path);
  if ($resized_image_path === false) {
    // Get file extension to provide more specific error message
    $file_extension = strtolower(pathinfo($image_path, PATHINFO_EXTENSION));
    if (!in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])) {
      return "Error: Unsupported image format. Only JPG, PNG, and GIF formats are supported.";
    } else {
      return "Error: Could not process image for API submission. The file may be corrupted or in an invalid format.";
    }
  }

  // Base64 encode the image
  $image_data = base64_encode(file_get_contents($resized_image_path));

  // Clean up temp file if we created one
  if ($resized_image_path !== $image_path) {
    unlink($resized_image_path);
  }

  // Prepare the API request
  $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $api_key;
  $headers = [
    'Content-Type: application/json'
  ];

  $data = [
    'contents' => [
      [
        'parts' => [
          [
            'text' => $user_prompt
          ],
          [
            'inline_data' => [
              'mime_type' => 'image/jpeg',
              'data' => $image_data
            ]
          ]
        ]
      ]
    ],
    'generation_config' => [
      'max_output_tokens' => 300,
      'temperature' => 0.4
    ]
  ];

  // Set up retry mechanism
  $max_retries = 3;
  $retry_count = 0;
  $retry_delay = 3; // seconds

  while ($retry_count <= $max_retries) {
    // Initialize cURL session
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    // Execute cURL session and get the response
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
      return "Error: $err";
    }

    $response_data = json_decode($response, true);

    // Check for successful response
    if (isset($response_data['candidates'][0]['content']['parts'][0]['text'])) {
      return trim($response_data['candidates'][0]['content']['parts'][0]['text']);
    }
    // Check for quota exhaustion error
    elseif (
      isset($response_data['error']) &&
      strpos($response_data['error']['message'], 'Resource has been exhausted') !== false
    ) {
      // If we haven't exceeded max retries, wait and try again
      if ($retry_count < $max_retries) {
        $retry_count++;
        sleep($retry_delay);
        continue;
      }
    }

    // If we get here, either it's a different error or we've exceeded retries
    if (isset($response_data['error'])) {
      return "API Error: " . $response_data['error']['message'];
    } else {
      return "Error: Unexpected API response format.";
    }
  }

  // This should not be reached, but just in case
  return "Error: Maximum retries exceeded for API quota exhaustion.";
}

// Helper function to resize image if needed
function chatgpt_resize_image($image_path)
{
  // Check if file exists
  if (!file_exists($image_path)) {
    return false;
  }

  // Validate image format first
  $image_info = @getimagesize($image_path);
  if ($image_info === false) {
    return false; // Not a valid image file
  }

  // Check if image format is supported
  $mime = $image_info['mime'];
  if (!in_array($mime, ['image/jpeg', 'image/png', 'image/gif'])) {
    return false; // Unsupported image format
  }

  // OpenAI recommends images under 20MB for best performance
  $max_file_size = 4 * 1024 * 1024; // 4MB for safety
  $max_dimension = 2048; // Max dimension for either width or height

  // Check file size
  $file_size = filesize($image_path);
  if ($file_size <= $max_file_size) {
    // Get dimensions
    $width = $image_info[0];
    $height = $image_info[1];
    if ($width <= $max_dimension && $height <= $max_dimension) {
      return $image_path; // No resizing needed
    }
  }

  // Need to resize the image
  $temp_file = tempnam(sys_get_temp_dir(), 'piwigo_captioner_');

  // Get image dimensions (we already have them from earlier validation)
  $width = $image_info[0];
  $height = $image_info[1];

  // Calculate new dimensions
  // Check for zero dimensions to prevent division by zero
  if ($width <= 0 || $height <= 0) {
    return false; // Cannot process an image with invalid dimensions
  }

  $ratio = min($max_dimension / $width, $max_dimension / $height);
  $new_width = round($width * $ratio);
  $new_height = round($height * $ratio);

  // Create image resource based on file type
  // We already have $mime from earlier validation
  switch ($mime) {
    case 'image/jpeg':
      $source = imagecreatefromjpeg($image_path);
      break;
    case 'image/png':
      $source = imagecreatefrompng($image_path);
      break;
    case 'image/gif':
      $source = imagecreatefromgif($image_path);
      break;
    default:
      return false;
  }

  if (!$source) {
    return false;
  }

  // Create destination image
  $destination = imagecreatetruecolor($new_width, $new_height);
  if (!$destination) {
    imagedestroy($source);
    return false;
  }

  // Handle transparency for PNG and GIF
  if ($mime == 'image/png' || $mime == 'image/gif') {
    imagealphablending($destination, false);
    imagesavealpha($destination, true);
    $transparent = imagecolorallocatealpha($destination, 255, 255, 255, 127);
    imagefilledrectangle($destination, 0, 0, $new_width, $new_height, $transparent);
  }

  // Resize
  imagecopyresampled(
    $destination,
    $source,
    0,
    0,
    0,
    0,
    $new_width,
    $new_height,
    $width,
    $height
  );

  // Save resized image
  switch ($mime) {
    case 'image/jpeg':
      imagejpeg($destination, $temp_file, 90);
      break;
    case 'image/png':
      imagepng($destination, $temp_file, 7);
      break;
    case 'image/gif':
      imagegif($destination, $temp_file);
      break;
  }

  // Clean up
  imagedestroy($source);
  imagedestroy($destination);

  return $temp_file;
}

// Function to update an image's description with the generated caption
function chatgpt_update_description($image_id, $caption)
{
  // Get current comment
  $query = "SELECT comment FROM " . IMAGES_TABLE . " WHERE id = " . $image_id;
  $result = pwg_query($query);
  $image = pwg_db_fetch_assoc($result);

  $current_comment = isset($image['comment']) ? $image['comment'] : '';

  // Prepare the new comment
  $new_comment = $current_comment;
  if (!empty($current_comment)) {
    $new_comment .= "\n\n";
  }
  $new_comment .= "AI Caption: " . $caption;

  // Update the image description in the database
  $query = "
      UPDATE " . IMAGES_TABLE . "
      SET comment = '" . pwg_db_real_escape_string($new_comment) . "'
      WHERE id = " . $image_id;

  pwg_query($query);
}
