<?php
/************************
 * 
 * 
 * Hjälpfunktioner
 * 
 * Inkludera med: require_once('functions.php');
 * 
 */
function deleteDir($dir, $days) {
   // open the directory
   $dhandle = opendir($dir);

   if ($dhandle) {
      // loop through it
      while (false !== ($fname = readdir($dhandle))) {
         // if the element is a directory, and 
         // does not start with a '.' or '..'
         // we call deleteDir function recursively 
         // passing this element as a parameter
         if (is_dir( "{$dir}/{$fname}" )) {
            if (($fname != '.') && ($fname != '..')) {
               echo "<u>Deleting Files in the Directory</u>: {$dir}/{$fname} <br />";
               deleteDir("$dir/$fname", $days);
            }
         // the element is a file, so we delete it
         } else {
            //datumfilter
            $now   = time();
            if ($now - filemtime("{$dir}/{$fname}") >= 60 * 60 * 24 * $days) {
                echo "Deleting File: {$dir}/{$fname} <br />";
                unlink("{$dir}/{$fname}");
            }
            
         }
      }
      closedir($dhandle);
    }
}
?>