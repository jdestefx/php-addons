<?php

   class Head {

      private static $loadDetails = null;
      private static $packageDetails = null;

      private static $cssFilesToWrite = [];
      private static $jsFilesToWrite = [];
      private static $partsFilesToWrite = [];

      private static $cssModFilesToWrite = [];
      private static $jsModFilesToWrite = [];

      private static $jsFileEntryFormat = "<script type=\"text/javascript\" src=\":FILE\"></script>";
      private static $cssFileEntryFormat = "<link type=\"text/css\" rel=\"stylesheet\" href=\":FILE\">";



      private static $pad = "      ";

      public static function init() {
         self::$loadDetails = json_encode($_GET);
         self::loadPackageDetails();

      }

      public static function loadPackageDetails() {
         if (file_exists("package.json")==false) {
            echo "Didn't find package.json";
            return false;
         }

         self::$packageDetails = json_decode(file_get_contents("package.json"));

         if (self::$packageDetails===null) {
            echo "<pre>CRITICAL FAILURE: INVALID PACKAGE.JSON FILE. MAKE SURE THE FILE IS PARSEABLE JSON.</pre>\n";
            die();
            return false;
         }

         if (isset(self::$packageDetails->indexFiles)==true) self::processIndexFiles();
         if (isset(self::$packageDetails->loadModules)==true) self::processModules();

         return true;
      }

      public static function unlinkFile($file) {
         if (file_exists($file)) unlink($file);
      }

      public static function write() {
         $pad = self::$pad;

         if (self::$packageDetails->combine==false) {
            self::unlinkFile("modules.js");
            self::unlinkFile("modules.css");
         }

         if (sizeof(self::$partsFilesToWrite)==0) self::unlinkFile("parts.html");

         echo "\n";
         foreach (self::$cssFilesToWrite as $file) echo "$pad".self::render($file)."\n";
         foreach (self::$jsFilesToWrite as $file) echo "$pad".self::render($file)."\n";
         echo "\n";

      }

      public static function render($file) {
         switch (pathinfo($file)['extension']) {
            case "js":
               return str_replace(":FILE", $file, self::$jsFileEntryFormat);
               break;
            case "css":
               return str_replace(":FILE", $file, self::$cssFileEntryFormat);
               break;
         }

         return "<unk>$file</unk>";
      }

      public static function processIndexFiles() {

         foreach (self::$packageDetails->indexFiles as $section) {

            if (isset($section->files)===false) continue;
            if (isset($section->output)===false) continue;
            if (sizeof($section->files)==0) continue;

            if (self::$packageDetails->combine==true) {
               $outputFile = $section->output;
               file_put_contents($outputFile, "");
               foreach ($section->files as $file) file_put_contents($outputFile, file_get_contents($file), FILE_APPEND);
               foreach ($section->last as $file) file_put_contents($outputFile, file_get_contents($file), FILE_APPEND);
               if (pathinfo($outputFile)['extension']=="css") self::$cssFilesToWrite[] = $outputFile;
               if (pathinfo($outputFile)['extension']=="js") self::$jsFilesToWrite[] = $outputFile;
            } else {
               if (isset($section->output)==true) self::unlinkFile($section->output);

               foreach ($section->files as $file) {
                  if (file_exists($file)===false) {
                     syslog(LOG_ERR, "$file mentioned in indexFiles, but not present on disk!");
                     continue;
                  }
                  if (pathinfo($file)['extension']=="css") self::$cssFilesToWrite[] = $file;
                  if (pathinfo($file)['extension']=="js") self::$jsFilesToWrite[] = $file;
               }
               
               if (isset($section->last)==true) {
                  foreach ($section->last as $file) {
                     if (file_exists($file)===false) {
                        syslog(LOG_ERR, "$file mentioned in indexFiles, but not present on disk!");
                        continue;
                     }
                     if (pathinfo($file)['extension']=="css") self::$cssFilesToWrite[] = $file;
                     if (pathinfo($file)['extension']=="js") self::$jsFilesToWrite[] = $file;
                  }
               }

            }

         }
      }

      public static function processModules() {
         foreach (self::$packageDetails->loadModules as $module) {
            $path = $module->path;
            $moduleName = pathinfo($path)["filename"];

            if (self::$packageDetails->combine==true) {
               if (file_exists("$path/$moduleName.js")===true) self::$jsModFilesToWrite[] = "$path/$moduleName.js";
               if (file_exists("$path/$moduleName.css")===true) self::$cssModFilesToWrite[] = "$path/$moduleName.css";
            } else {
               if (file_exists("$path/$moduleName.js")===true) self::$jsFilesToWrite[] = "$path/$moduleName.js";
               if (file_exists("$path/$moduleName.css")===true) self::$cssFilesToWrite[] = "$path/$moduleName.css";
            }

            if (file_exists("$path/$moduleName.html")===true) self::$partsFilesToWrite[] = "$path/$moduleName.html";

         }

         if (sizeof(self::$jsModFilesToWrite)>0) {
            file_put_contents("modules.js", "");
            self::$jsFilesToWrite[] = "modules.js";
         }

         if (sizeof(self::$cssModFilesToWrite)>0) {
            file_put_contents("modules.css", "");
            self::$cssFilesToWrite[] = "modules.css";
         }


         if (sizeof(self::$partsFilesToWrite)>0) {
            file_put_contents("parts.html", "");
            //self::$partsFilesToWrite[] = "parts.html";
         }

         if (self::$packageDetails->combine==true) {
            foreach (self::$jsModFilesToWrite as $file) file_put_contents("modules.js", file_get_contents($file), FILE_APPEND);
            foreach (self::$cssModFilesToWrite as $file) file_put_contents("modules.css", file_get_contents($file), FILE_APPEND);
         }

         file_put_contents("parts.html", "<parts>\n", FILE_APPEND);
         foreach (self::$partsFilesToWrite as $file) file_put_contents("parts.html", file_get_contents($file)."\n", FILE_APPEND);
         file_put_contents("parts.html", "</parts>\n", FILE_APPEND);

      }

   }

   \Head::init();

   if (isset($argv[1])===true) {
      if ($argv[1]=="--writeIndex") {
         echo "<html>\n<head>\n";
         \Head::write();
         echo "\n</head>\n<body></body>\n</html>\n";
      }
   }

?>
