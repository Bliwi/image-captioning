<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

// Table name for the queue
global $prefixeTable;
define('CHATGPT_QUEUE_TABLE', $prefixeTable . 'chatgpt_queue');

/**
 * Create the queue table if it doesn't exist
 */
function chatgpt_create_queue_table() {
  $query = '
    CREATE TABLE IF NOT EXISTS ' . CHATGPT_QUEUE_TABLE . ' (
      id int(11) NOT NULL AUTO_INCREMENT,
      image_id int(11) NOT NULL,
      date_added datetime NOT NULL,
      status varchar(20) NOT NULL DEFAULT \'pending\',
      error_message text,
      PRIMARY KEY (id),
      KEY image_id (image_id),
      KEY status (status)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8;
  ';
  
  pwg_query($query);
}

/**
 * Add images to the processing queue
 * 
 * @param array $image_ids Array of image IDs to process
 * @return int Number of images added to the queue
 */
function chatgpt_queue_images($image_ids) {
  if (empty($image_ids)) {
    return 0;
  }
  
  $now = date('Y-m-d H:i:s');
  $added = 0;
  
  // Add each image to the queue
  foreach ($image_ids as $image_id) {
    // Check if image is already in the queue
    $query = 'SELECT id FROM ' . CHATGPT_QUEUE_TABLE . ' WHERE image_id = ' . $image_id . ' AND status = \'pending\'';
    $result = pwg_query($query);
    
    if (pwg_db_num_rows($result) == 0) {
      $query = 'INSERT INTO ' . CHATGPT_QUEUE_TABLE . ' (image_id, date_added, status) VALUES (' . $image_id . ', \'' . $now . '\', \'pending\')';
      pwg_query($query);
      $added++;
    }
  }
  
  return $added;
}

/**
 * Get pending images from the queue
 * 
 * @param int $limit Maximum number of images to retrieve
 * @return array Array of image IDs to process
 */
function chatgpt_get_pending_images($limit = 5) {
  $query = 'SELECT image_id FROM ' . CHATGPT_QUEUE_TABLE . ' WHERE status = \'pending\' ORDER BY date_added ASC LIMIT ' . $limit;
  $result = pwg_query($query);
  
  $image_ids = array();
  while ($row = pwg_db_fetch_assoc($result)) {
    $image_ids[] = $row['image_id'];
  }
  
  return $image_ids;
}

/**
 * Mark images as being processed
 * 
 * @param array $image_ids Array of image IDs being processed
 */
function chatgpt_mark_images_processing($image_ids) {
  if (empty($image_ids)) {
    return;
  }
  
  $query = 'UPDATE ' . CHATGPT_QUEUE_TABLE . ' SET status = \'processing\' WHERE image_id IN (' . implode(',', $image_ids) . ')';
  pwg_query($query);
}

/**
 * Mark an image as completed
 * 
 * @param int $image_id Image ID that was processed
 */
function chatgpt_mark_image_completed($image_id) {
  $query = 'UPDATE ' . CHATGPT_QUEUE_TABLE . ' SET status = \'completed\' WHERE image_id = ' . $image_id;
  pwg_query($query);
}

/**
 * Mark an image as failed
 * 
 * @param int $image_id Image ID that failed processing
 * @param string $error_message Error message
 */
function chatgpt_mark_image_failed($image_id, $error_message) {
  $query = 'UPDATE ' . CHATGPT_QUEUE_TABLE . ' SET status = \'failed\', error_message = \'' . pwg_db_real_escape_string($error_message) . '\' WHERE image_id = ' . $image_id;
  pwg_query($query);
}

/**
 * Get queue statistics
 * 
 * @return array Statistics about the queue
 */
function chatgpt_get_queue_stats() {
  $stats = array(
    'pending' => 0,
    'processing' => 0,
    'completed' => 0,
    'failed' => 0,
    'total' => 0
  );
  
  $query = 'SELECT status, COUNT(*) as count FROM ' . CHATGPT_QUEUE_TABLE . ' GROUP BY status';
  $result = pwg_query($query);
  
  while ($row = pwg_db_fetch_assoc($result)) {
    if (isset($stats[$row['status']])) {
      $stats[$row['status']] = $row['count'];
      $stats['total'] += $row['count'];
    }
  }
  
  return $stats;
}

/**
 * Clean up old completed/failed entries
 * 
 * @param int $days Number of days to keep entries
 */
function chatgpt_cleanup_queue($days = 7) {
  $cutoff_date = date('Y-m-d H:i:s', time() - ($days * 86400));
  $query = 'DELETE FROM ' . CHATGPT_QUEUE_TABLE . ' WHERE status IN (\'completed\', \'failed\') AND date_added < \'' . $cutoff_date . '\'';
  pwg_query($query);
}
?>