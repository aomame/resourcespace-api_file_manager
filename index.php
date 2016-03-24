<?php
$site_root = $_SERVER['DOCUMENT_ROOT'];
$api=true;
include "$site_root/include/db.php";
include "$site_root/include/general.php";
include "$site_root/include/search_functions.php";
include "$site_root/include/collections_functions.php";
include "$site_root/include/resource_functions.php";
include "$site_root/include/authenticate.php";

// required: check that this plugin is available to the user
if (!in_array("api_file_manager",$plugins)){die("no access");}



// get params of the current request
// ============================================================
// general params
$user_id=getval("user_id","");
$file_id=getval("file_id","");
$file_name=getval("file_name","");
$collection_id=getval("collection_id","");
$collection_name=getval("collection_name","");
$hidden_collections=getval("hidden_collections","");
$remove_unserscore=getval("remove_unserscore",FALSE);
$download_size=getval("download_size","");
$last_sync=getval("last_sync","");
$has_new_version_of_file=getval("has_new_version_of_file", FALSE);

// get
$get=getval("get",FALSE);
$get_original_file_name=getval("get_original_file_name",FALSE);
$get_file_id=getval("get_file_id",FALSE);
$get_all_collections=getval("get_all_collections",FALSE);
$get_files_in_collection=getval("get_files_in_collection",FALSE);
$get_file_link=getval("get_file_link","");
$get_file_thumbnail_link=getval("get_file_thumbnail_link","");
$get_file_preview_link=getval("get_file_preview_link", "");
$get_file_exists=getval("get_file_exists", FALSE);

// delete
$delete_file=getval("delete_file",FALSE);
$delete_collection=getval("delete_collection",FALSE);

// create
$create_collection=getval("create_collection","");

// update \\ move
$set=getval("set",FALSE);
$move=getval("move",FALSE);
$update_file_size=getval("update_file_size",FALSE);
$new_file_name=getval("new_file_name", "");
$new_collection_name=getval("new_collection_name", "");
$move_to_collection=getval("move_to_collection", "");





// Authenticate
// ============================================================
if ($api_file_manager['signed']){

  // test signature? get query string minus leading ? and skey parameter
  $test_query="";
  parse_str($_SERVER["QUERY_STRING"],$parsed);
  foreach ($parsed as $parsed_parameter=>$value){
    if ($parsed_parameter!="skey"){
      $test_query.=$parsed_parameter.'='.$value."&";
    }
  }
  $test_query=rtrim($test_query,"&");

  // get hashkey that should have been used to create a signature.
  $hashkey=md5($api_scramble_key.getval("key",""));

  // generate the signature required to match against given skey to continue
  $keytotest = md5($hashkey.$test_query);

  if ($keytotest <> getval('skey','')){
    header("HTTP/1.0 403 Forbidden.");
    echo "HTTP/1.0 403 Forbidden. Invalid Signature";
    exit;
  }
}




/**
 * print JSON data
 * @param  string|array $data to encode and print out as JSON
 * @param  boolean $prettify
 */
function printJson($data, $prettify=false){
  header('Content-type: application/json');
  if($prettify){
    print json_encode($data, JSON_PRETTY_PRINT);  
  }else{
    print json_encode($data);
  }
}



// GET
// ============================================================
//  - get a specific file by file ID
//  - get link to specific file by file ID
//  - get link to specific file thumbnail by file ID 
//  - get all collections
//  - get all files within a specific collection
//  - get the collection ID by collection name
//  - get the original file name by file ID
//  - get the ID of a file by its original name: file_name.pdf
//  - check if a file exists in a collection
//  ============================================================
  
// get a specific file by file ID
if($get && $file_id){
  $file = sql_query("SELECT * FROM resource WHERE ref='$file_id'");

  $collectionQuery =  "SELECT ref, name FROM collection WHERE ref IN (SELECT collection FROM collection_resource WHERE resource LIKE '$file_id')"; 
  $collectionData = sql_query($collectionQuery);

  $file[0]['collection_id']=$collectionData[0]['ref'];
  $file[0]['collection_path']= str_replace(' ','_', $collectionData[0]['name']); 
  
  printJson($file);
}


// get link to specific file by file ID
// $get_file_link must be the ID of the file
if(!empty($get_file_link)){
  $file = sql_query("SELECT * FROM resource WHERE ref='$get_file_link'");
  $original_link = get_resource_path($file[0]['ref'], FALSE, '', FALSE, $file[0]['file_extension'], -1, 1, FALSE, '', -1);
  printJson($original_link);
}

// get link to specific file thumbnail by file ID
// $get_file_thumbnail_link must be the ID of the file
if(!empty($get_file_thumbnail_link)){

  $resource = $get_file_thumbnail_link;
  $file = sql_query("SELECT * FROM resource WHERE ref='$resource'");
  
  $preview_extension = $file[0]['file_extension'];
  if($preview_extension == 'pdf'){
    $preview_extension = $file[0]['preview_extension'];
  }
  $thumbnail_link = get_resource_path($resource, FALSE, 'col', FALSE, $preview_extension, -1, 1, FALSE);
  $file_headers = get_headers($thumbnail_link);
  $resourcedata = get_resource_data($resource);
  $no_preview_icon = "gfx/".get_nopreview_icon($resourcedata["resource_type"],$resourcedata["file_extension"],false);
  $data = array(
    'thumbnail_link' => $thumbnail_link,
    'no_preview_icon' => $no_preview_icon,
    'file_header' => $file_headers[0]  
  );
  printJson($data);
}


// get all collections
if($get && $get_all_collections){
  $all_collections = get_user_collections($user_id);
  printJson($all_collections);
}


// get all files within a specific collection
if($get_files_in_collection && $collection_id){

  $fetchFiles = "SELECT * 
            FROM resource 
            WHERE 
              ref IN (SELECT resource FROM collection_resource WHERE collection='$collection_id')";
  $fetchCollection = "SELECT ref, name FROM collection WHERE ref='$collection_id'";


  $fileData = sql_query($fetchFiles);
  $collectionData = sql_query($fetchCollection);

    

  foreach($fileData as $k => $file){

    // Get the size of the original file
    $filepath = get_resource_path($file['ref'], TRUE, '', FALSE, $file['file_extension'], -1, 1, FALSE, '', -1);
    $original_size = get_original_imagesize($file['ref'], $filepath, $file['file_extension']);
    $original_size = formatfilesize($original_size[0]);
    $original_size = str_replace('&nbsp;', ' ', $original_size);
    $fileData[$k]['original_size'] = $original_size;

    // file link
    $fileData[$k]['original_link'] =  get_resource_path($file['ref'], FALSE, '', FALSE, $file['file_extension'], -1, 1, FALSE, '', -1);
    
    // collection
    $fileData[$k]['collection_id'] = $collectionData[0]['ref'];
    $fileData[$k]['collection_path'] = $collectionData[0]['name'];
  }
  printJson($fileData);
}


// get the collection ID by collection name
if($get && !empty($collection_name)){
  $collection_name = urldecode($collection_name);
  if($remove_unserscore){
    $collection_name = str_replace('_', ' ', $collection_name);
  }
  $collection_id = sql_query("SELECT ref FROM collection WHERE name LIKE '$collection_name'");
  printJson($collection_id);
}


// get the original file name by file ID
if($get_original_file_name && $file_id) {
  $fileName = sql_query("SELECT value FROM resource_data WHERE resource_type_field=51 AND resource=$file_id");
  $data = array('original_file_name' => $fileName[0]['value']);
  printJson($data);
}


// get the ID of a file by its original name: file_name.pdf
if($get_file_id && $file_name){

  $file_name = urldecode($file_name);
  
  //get all file ID's with name $file_name
  $fileIds = sql_query("SELECT resource FROM resource_data WHERE resource_type_field=51 AND value LIKE '%$file_name%'");

  if($collection_id){
    // get only the file from collection $collection_id  
    foreach($fileIds as $file){
      $ref = $file['resource'];
      $s = sql_query("SELECT resource FROM collection_resource WHERE collection=$collection_id AND resource=$ref");

      if(!empty($s[0]['resource'])){ 
        $fileId = $s[0]['resource'];
      }

    }
  }else{
    $fileId = $fileIds[0]['resource'];
  }
  
  $fileId = $fileId ? $fileId : 0;
  $data = array(
    'file_id' => $fileId,
    'collection_id' => $collection_id
  );
  printJson($data);
}

// check if a file exists in a collection
// return true when file exists
if($get_file_exists && $collection_id && $file_id){
  $fileInCollection = FALSE;
  $selectFiles = sql_query("SELECT resource FROM collection_resource WHERE collection=$collection_id AND resource=$file_id");
  if(!empty($selectFiles[0]['resource'])){
    $fileInCollection = TRUE;
  }
  $data = array('file_exists_in_collection' => $fileInCollection);
  printJson($data);
}


// get downscaled preview of an original image
if($get_file_preview_link){
  
  // sizes: $download_size
  // -----------------------------
  // thm - Thumbnail
  // pre - Preview
  // scr - Screen
  // lpr - Low resolution print (default)
  // hpr - High resolution print
  // col - Collection
  // '' (empty) - original file
  $download_size = $download_size ? $download_size : ''; 
  $preview_link = get_resource_path($get_file_preview_link,false,$download_size,true,'jpg',-1,1,false,'',-1);
  $file_headers = get_headers($preview_link);

  $data = array(
    'preview_link' => $preview_link,
    'file_header' => $file_headers[0] 
  );
  printJson($data);
}

// check if version of file exists
if($has_new_version_of_file){
  
  $hasNewVersion = false;
  
  $lastModified = sql_query("SELECT file_modified FROM resource WHERE ref='$file_id'");
  $lastModified = $lastModified[0]['file_modified'];
  
  if($lastModified){
    $rsLastModified = strtotime($lastModified);
    $lastSync = intval($last_sync);
    if($rsLastModified > $lastSync){
      // file has changed
      $hasNewVersion = true;
    }else{
      // files hasn't changes
      $hasNewVersion = false;
    }    
  }else{
    // file has never been changed since first upload
    $hasNewVersion = false;
  }
  
  printJson($hasNewVersion);
  // Debug
  // printJson(array(
  //   'last-synced' => $lastSync,
  //   'RS last Modified' => $rsLastModified,
  //   'RS string' => $lastModified,
  //    'has New Version' => $hasNewVersion
  // ));
  
}




// DELETE
// ============================================================
// - delete file from collection
// - delete collection and all files in the collection
// ============================================================

// delete file
if($delete_file && $file_id){ 
  delete_resource($file_id);
  printJson(true);
}

// delete collection and all files in the collection
if($delete_collection && $collection_id){
  $delete = delete_collection($collection_id);
  printJson($delete);
} 








// CREATE
// ============================================================
// - create a new collection
// ============================================================

// create a new collection
// returns id of new collection
if($create_collection && $collection_name && $user_id){
  $allowchanges = 1;
  $cant_delete = 0;
  $collection_name = urldecode($collection_name);
  if($remove_unserscore){
    $collection_name = str_replace('_', ' ', $collection_name);  
  }
  $create = create_collection($user_id,$collection_name, $allowchanges, $cant_delete);
  $create = array('new_collection' => $create);
  printJson($create);
}







// UPDATE
// ============================================================
// - set a new user to a file
// - set file size : no size set when file uploaded by API, execute after file upload
// - rename a file
// - rename a collection
// - move file to another collection
// ============================================================

// set a new user to a file
if($set && $file_id && $user_id){
  $setUser = sql_query("UPDATE resource SET created_by='$user_id' WHERE ref='$file_id'");
  $newUser = array('new_user' => $user_id, 'file' => $file_id);
  printJson($newUser);
}

// set file size : no size set when file uploaded by API
// execute after file upload
if($set && $update_file_size && $file_id){
  update_disk_usage($file_id);
}

// rename a file
if($set && $file_id && $new_file_name){
  $new_file_name = urldecode($new_file_name); 
  $renameFile = sql_query("UPDATE resource_data SET value=\"$new_file_name\" WHERE resource_type_field=51 AND resource='$file_id'");
  $newFileName = array('new_file_name' => $new_file_name, 'file' => $file_id);
  printJson($newFileName);
}

// rename a collection
if($set && $collection_id && $new_collection_name){
  $new_collection_name = urldecode($new_collection_name);
  $new_collection_name = str_replace('_', ' ', $new_collection_name);
  $renameCollection = sql_query("UPDATE collection SET name=\"$new_collection_name\" WHERE ref='$collection_id'");
  $newCollectionName = array('new_folder_name' => $new_collection_name); 
  printJson($newCollectionName);
}

// move file to another collection
if($set && $move_to_collection && $collection_id && $file_id){
  $moveToCollection = sql_query("UPDATE collection_resource SET collection='$move_to_collection' WHERE collection='$collection_id' AND resource='$file_id'");
  $data = array(
    'old_collection' => $collection_id, 
    'new_collection' => $move_to_collection, 
    'file' => $file_id
  );
  printJson($data);
}
