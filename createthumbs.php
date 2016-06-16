<?php
        ini_set('max_execution_time', 900);
		// This script checks the database and files to make sure every first image has a corresponding thumbnail
		// If they do not have the thumbnail, it creates it
		// If it does exist but not stored in the database, we just store the file's name
        function createThumb($iName, $Picture1, $picURL){
                $picData = "/images/$picURL/$Picture1";
                $test = @file_get_contents($picData);
                if(!$test){
                        echo "$picData does not exist.\r\n";
                        return null;
                }
                $iHandle = @imagecreatefromjpeg($picData);
                if($iHandle === false) $iHandle = @imagecreatefrompng($picData);
                if($iHandle === false) $iHandle = @imagecreatefromgif($picData);
                if($iHandle === false) $iHandle = @imagecreatefromstring($picData);
                if($iHandle !== false){
                        $oWidth = imagesx($iHandle);
                        $oHeight = imagesy($iHandle);
                        if(($oWidth > 230) || ($oHeight > 173)){
                                $width = $oWidth;
                                $height = $oHeight;
                                if($width > 230){
                                        $ratio = 230 / $width;
                                        $width = ceil($width * $ratio);
                                        $height = ceil($height * $ratio);

                                }
                                if($height > 173){
                                        $ratio = 173 / $height;
                                        $width = ceil($width * $ratio);
                                        $height = ceil($height * $ratio);

                                }
                                $iHandleNew = imagecreatetruecolor($width, $height);
                                imagecopyresampled($iHandleNew, $iHandle, 0, 0, 0, 0, $width, $height, $oWidth, $oHeight);
                                imagedestroy($iHandle);
                                $iHandle = $iHandleNew;
                        }
                        $fileName = $iName . "Thumb.jpg";
                        $filePath = "/images/$picURL/$fileName";
                        imagejpeg($iHandle, $filePath, 75);
                        imagedestroy($iHandle);
                        chown($filePath, "image");
                        return $fileName;
                }else return null;
        }

        //parse command-line options
                $optionBuffer = implode(" ", $_SERVER['argv']);
                $optionBuffer = preg_split("/(\-[^\s]+)|\,+/", $optionBuffer, null, (PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE));

                $cronOption = array();
                $lastOption = "";

                foreach($optionBuffer as $option){
                        if(substr($option, 0, 1) == "-"){
                                $cronOption[$option] = array();
                                $lastOption = $option;

                        }elseif(($lastOption != "") && trim($option)) $cronOption[$lastOption][] = trim($option);

                }

        require_once("conn.php");
        if(!isset($cronOption["-nTh"])){
                $sql = "SELECT * FROM Table1";
                $rs = mysqli_query($conn00, $sql);
                while(list($Acrn) = mysqli_fetch_row($rs)){
                        $cronOption["-nTh"][] = $Acrn;
                }
        }

        foreach($cronOption["-nTh"] as $key => $Acronym){
                $sql = "SELECT * FROM Table1 WHERE Project = '$Acronym'";
                $rs = mysqli_query($conn00, $sql);
                list($ConnNo, $IncludeFile, $database) = mysqli_fetch_row($rs);
                include("/includes/$IncludeFile.php");
                $configConn = $$ConnNo;
                if(isset($configConn)){
                        echo "Connected to $Acronym=====\r\n";
                }
                $sql = "SELECT i.UniqueID, i.iName, i.Picture1, p.PicURL FROM $database.i JOIN $database.p WHERE i.Picture0 = '' AND i.Picture1 != '' ";
                $sql .= " ORDER BY p.PicURL ASC";
                $rs = mysqli_query($configConn, $sql);
                if(mysqli_errno($configConn)){
                        echo $sql . " : " . mysqli_error($configConn) . "\r\n";
                        die();
                }
                $added = 0;
                $count = 0;
                $failed = 0;
                $existed = 0;
                while(list($UniqueID, $iName, $Picture1, $PicURL) = mysqli_fetch_row($rs)){
                        if(file_exists("/images/$PicURL/$iName" . "Thumb.jpg")){
                                $Picture0 = $iName . "Thumb.jpg";
                                $existed++;
                        }
                        else{
                                $Picture0 = createThumb($iName, $Picture1, $PicURL);
                                $count++;
                        }
                        if($Picture0 != null){
                                $updateSQL = "UPDATE $database.i SET Picture0 = '$Picture0' WHERE UniqueID = '$UniqueID' ";
                                $qUpdate = mysqli_query($configConn, $updateSQL);
                                if(mysqli_errno($configConn)) echo mysqli_error($configConn) . "\r\n";
                                $added++;
                        }
                        else{
                                echo $Picture1 . " Thumbnail not created. \r\n";
                                $failed++;
                        }
                }
                echo "$count images created \r\n";
                echo "$existed images existed \r\n";
                echo "$added images added \r\n";
                echo "$failed images failed \r\n";
                echo "=====\r\n";
        }
?>
