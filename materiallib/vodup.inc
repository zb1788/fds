<?php
// 文件大小限制（单位：M）
$fileSize = 0;

// 保存文件的根目录
$savePath = "/home/upload/localpath/teach_pre_dir";

// 上传的文件参数名
$fileName = "file";

// 上传的相对路径参数名
$realPath = "path";

// base64编码过的文件字符串参数名
$base64_str = "filestr";

// base64解码后要存储的文件名
$base64_file = "filename";

// 允许上传的文件类型
$uploadAllowTypes = array('amr','ogg','wma','swf','flac','csv','xls','xlsx','png','jpg','txt','htm','html','264aviib','264aviif','avi','mpeg','mpeg1','m1v','mpeg2','m2v','mpeg4','m4v','mp4','mpg','mjpg','mjpeg','ram','yuv','mpe','dv','dat','divx','div','dif','xvid','mov','h261','h263','h264','264avi','264ts','asx','flv','rm','rmvb','mkv','wmv','3gp','3g2','amv','vob','asf','ogm','other','mp3','wav','pdf','jpeg','gif','svg','tiff','bmp','dwg','rtp','xml','dbx','pst','doc','docx','ppt','pptx','mdb','zip','rar','mov','asf');

// 上传时是否检验文件格式真实性
$check_mimetype = 1;
