<?php
/*
Plugin Name: Sos Plugin
Description: Show sos inventroy data
Version: 1.0
*/



function load_token()
{
  $code = '387652c7befb4f4fa1a13bfbfb65fcbf06070fbb8b1e4b59be5ff0d8fec144e1';
  $redirect_uri = 'https://techinnglobal.com/sos_inventory/';
  $client_id = '3a57ff6efa0a400097bf4c653922a886';
  $secret = 'qIMWFxfCNYjbJ6tCeY1wIiwucmOCPiJsOdIT';
  $url = "https://api.sosinventory.com/oauth2/token";

  $data = 'grant_type=authorization_code' .
    '&client_id=' . $client_id .
    '&client_secret=' . $secret .
    '&code=' . $code .
    '&redirect_uri=' . $redirect_uri;

  $curl = curl_init($url);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_POST, true);
  curl_setopt($curl, CURLOPT_POSTFIELDS,  $data);
  curl_setopt($curl, CURLOPT_HTTPHEADER, [
    "Content-Type: application/x-www-form-urlencoded",
    "Host: api.sosinventory.com"
  ]);
  $response = curl_exec($curl);
  $err = curl_error($curl);
  curl_close($curl);

  if ($err) {
    echo "cURL Error #:" . $err;
  } else {
    $responseObj = json_decode($response);
    $token = $responseObj->access_token;
    return $token;
  }
}
function get_token()
{

  $token = get_option('sos_token');
  if ($token == false) {
    $token = load_token();
    add_option('sos_token', $token);
  }
  return $token;
}
function get_item()
{

  $token = get_token();
  $url = 'https://api.sosinventory.com/api/v2/item';

  $curl = curl_init($url);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded',
    'Host: api.sosinventory.com',
    'Authorization: Bearer ' . $token
  ]);

  $response = curl_exec($curl);
  //echo $response;
  curl_close($curl);
  $itemsObj = json_decode($response);
  $count = $itemsObj->count;
  $data  = $itemsObj->data;

  $data_arr = array();

  foreach ($data as $item) {
    $post = array();
    $name = $item->name;
    $description = $item->description;
    $price = $item->minimumPrice;
    $sku = $item->sku;
    $sos_id = $item->id;
    $post['sos_id'] = $sos_id;
    $post['title'] = $name;
    $post['content'] = $description;
    $post['price'] = $price;
    $post['sku'] = $sku;
    $data_arr[] = $post;

    //echo "name=".$name. "description = $description price=$price <br/>";


  }
  save_item($data_arr);

  //  return $responseObj;
}
function save_item($data_arr)
{
  global $wpdb;
  $sos = array(
    "custom-field-1" => "price",
    "custom-field-2" => "sku",
    "custom-post-type" => "sos_inventory"
  );

  foreach ($data_arr as $post) {

    if (post_exists1($post["title"], $wpdb, $sos)) {
      continue;
    }

    $post["id"] = wp_insert_post(array(
      "post_title" => $post["title"],
      "post_content" => $post["content"],
      "post_type" => $sos["custom-post-type"],
      "post_status" => "publish"
    ));
    update_field($sos["custom-field-1"], $post["price"], $post["id"]);
    update_field($sos["custom-field-2"], $post["sku"], $post["id"]);
    add_post_meta($post["id"], 'sos_id', $post["sos_id"], true);
  }
}
function post_exists1($title, $wpdb, $sos)
{
  // Get an array of all posts within our custom post type
  $posts = $wpdb->get_col("SELECT post_title FROM {$wpdb->posts} WHERE post_type = '{$sos["custom-post-type"]}' AND post_status = 'publish' ");

  // Check if the passed title exists in array
  return in_array($title, $posts);
}


add_action('init', 'get_item');

function update_sos_items($post_ID, $post_after, $post_before)
{

  $sos_id = get_post_meta($post_ID, 'sos_id');
  $url = 'https://api.sosinventory.com/api/v2/item/' . $sos_id[0];
  $token = get_token();

  $data = 'name=' . $post_after->post_title .
    '&description=' . $post_after->post_content .
    '&minimumPrice=' . get_field('price', $post_ID) .
    '&sku=' . get_field('sku', $post_ID);

  $curl = curl_init($url);
  curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
  curl_setopt($curl, CURLOPT_POSTFIELDS,  $data);
  curl_setopt($curl, CURLOPT_HTTPHEADER, [
    "Content-Type: application/x-www-form-urlencoded",
    "Host: api.sosinventory.com",
    'Authorization: Bearer ' . $token
  ]);
  $response = curl_exec($curl);
  $err = curl_error($curl);
  curl_close($curl);

  if ($err) {
    echo "cURL Error #:" . $err;
  } else {
    // $responseObj = json_decode($response);
    // print_r($responseObj);
  }
}

add_action('post_updated', 'update_sos_items', 10, 3);
