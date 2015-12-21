## Resource API

#### Usage:
http://url/plugins/api_file_manager/?key=[authkey]&[optional parameters]

#### Parameters:

*get params of the current request*
============================================================

// general params
$user_id
$file_id
$file_name
$collection_id
$collection_name
$hidden_collections
$remove_unserscorescore

// get
$get
$get_original_file_name
$get_file_id
$get_all_collections
$get_files_in_collection
$get_file_link
$get_file_thumbnail_link
$get_file_preview_link
$get_file_exists

// delete
$delete_file
$delete_collection

// create
$create_collection

// update | move
$set
$move
$update_file_sizesize
$new_file_name
$new_collection_name
$move_to_collection