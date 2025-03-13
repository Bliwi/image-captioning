<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

function chatgpt_handle_batch_action($action, $collection) {
    global $page;
    
    if ($action !== 'chatgpt_caption') {
      return;
    }
    
    // Process each image individually
    $processed = 0;
    $errors = 0;
    $error_messages = array();
    
    foreach ($collection as $image_id) {
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
      
      // Get full path to original image
      $image_path = chatgpt_get_image_path($image);
      
      if (!file_exists($image_path)) {
        $errors++;
        $error_messages[] = "Image #$image_id: File not found at $image_path";
        continue;
      }
      
      // Generate caption for this image
      $caption = chatgpt_generate_caption($image_path);
      
      if (strpos($caption, 'Error:') === 0) {
        $errors++;
        $error_messages[] = "Image #$image_id: $caption";
        continue;
      }
      
      // Update description
      chatgpt_update_description($image_id, $caption);
      $processed++;
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
  
  // Function to generate captions has been simplified to process one image at a time
  // The batch processing functionality was removed as Gemini's API doesn't support it
  
  // Helper function to get the file path for an image
  function chatgpt_get_image_path($element) {
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
  function chatgpt_generate_caption($image_path) {
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
      return "Error: Could not process image for API submission.";
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
  
    // Execute cURL session and get the response
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
  
    if ($err) {
      return "Error: $err";
    }
  
    $response_data = json_decode($response, true);
    if (isset($response_data['candidates'][0]['content']['parts'][0]['text'])) {
      return trim($response_data['candidates'][0]['content']['parts'][0]['text']);
    } elseif (isset($response_data['error'])) {
      return "API Error: " . $response_data['error']['message'];
    } else {
      return "Error: Unexpected API response format.";
    }
  }
  
  // Helper function to resize image if needed
  function chatgpt_resize_image($image_path) {
    // OpenAI recommends images under 20MB for best performance
    $max_file_size = 4 * 1024 * 1024; // 4MB for safety
    $max_dimension = 2048; // Max dimension for either width or height
  
    // Check file size
    $file_size = filesize($image_path);
    if ($file_size <= $max_file_size) {
      // Get dimensions
      list($width, $height) = getimagesize($image_path);
      if ($width <= $max_dimension && $height <= $max_dimension) {
        return $image_path; // No resizing needed
      }
    }
  
    // Need to resize the image
    $temp_file = tempnam(sys_get_temp_dir(), 'piwigo_captioner_');
  
    // Get image dimensions
    list($width, $height) = getimagesize($image_path);
  
    // Calculate new dimensions
    $ratio = min($max_dimension / $width, $max_dimension / $height);
    $new_width = round($width * $ratio);
    $new_height = round($height * $ratio);
  
    // Create image resource based on file type
    $image_info = getimagesize($image_path);
    $mime = $image_info['mime'];
  
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
  function chatgpt_update_description($image_id, $caption) {
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
?>