<?php

class images_to_sprite {
  function images_to_sprite($folder,$outputurl,$output,$x,$y) {
    $this->folder = ($folder ? $folder : 'myfolder'); // Folder name to get images from, i.e. C:\myfolder or /home/user/Desktop/folder
    $this->filetypes = array('jpg'=>true,'png'=>true,'jpeg'=>true,'gif'=>true); // Acceptable file extensions to consider
    $this->output = ($output ? $output : 'mysprite'); // Output filenames, mysprite.png and mysprite.css
    $this->outputurl = ($outputurl ? $outputurl : '');  // Add URL path to output
    $this->x = $x; // Width of images to consider
    $this->y = $y; // Heigh of images to consider
    $this->files = array();
  }

  function add_image($file){
    $this->files[$file] = $file;
  }

  function create_sprite() {
/*
    $basedir = $this->folder;
    $files = array();

    // Read through the directory for suitable images
    if($handle = opendir($this->folder)) {
      while (false !== ($file = readdir($handle))) {
        $split = explode('.',$file);
        // Ignore non-matching file extensions
        if($file[0] == '.' || !isset($this->filetypes[$split[count($split)-1]]))
          continue;
        // Get image size and ensure it has the correct dimensions
        $output = getimagesize($this->folder.'/'.$file);
        echo "file:".$file."  ";
        //if($output[0] != $this->x && $output[1] != $this->y)
        //continue;
        // Image will be added to sprite, add to array
        $this->files[$file] = $file;
      }
      closedir($handle);
    }
*/
    // yy is the height of the sprite to be created, basically X * number of images
    $this->yy = $this->y * count($this->files);

    echo "folder:".$handle."  files=".count($this->files)."   y=".($this->yy)."   x=".($this->x);

    $im = imagecreatetruecolor($this->x,$this->yy);

    // Add alpha channel to image (transparency)
    imagesavealpha($im, true);
    $alpha = imagecolorallocatealpha($im, 0, 0, 0, 127);
    imagefill($im,0,0,$alpha);

    // Append images to sprite and generate CSS lines
    $i = $ii = 0;
    $fp = fopen($this->output.'.css','w');
    fwrite($fp,'.'.$this->output.' { width: '.$this->x.'px; height: '.$this->y.'px; background-image: url('.$this->outputurl.$this->output.'.png); text-align:center; }'."\n");
    foreach($this->files as $key => $file) {
      fwrite($fp,'.'.$this->output.(++$ii).' { background-position: -0px -'.($this->y*$i).'px; }'."\n");
//      $path=$this->folder.'/'.$file;
      $path=$file;
      switch(pathinfo($path,PATHINFO_EXTENSION)){
      case "png":
        $im2 = imagecreatefrompng($path);
        break;
      case "jpg": case "jpeg":
        $im2 = imagecreatefromjpeg($path);
        break;
      }
      //imagecopy($im,$im2,0,($this->y*$i),0,0,$this->x,$this->y);
      imagecopyresized ($im,$im2,0,($this->y*$i),0,0,$this->x,$this->y,imagesx($im2),imagesy($im2));
      $i++;
    }
    fclose($fp);
    imagepng($im,$this->output.'.png'); // Save image to file
    imagedestroy($im);
  }
}
class App {
  var $allowedOrigin = "*";
  var $filelist="./filelist.dat";
  var $datadir="d/";
  var $thumbname="sprite";
  var $maxfiles=100;
  var $pi, $urlthumbdir, $urldatadir, $urlthumbimg, $urlthumbcss;

  function __construct(){
    $this->prot=isset($_SERVER["HTTPS"]);
    $this->dname=pathinfo($_SERVER["SCRIPT_NAME"],PATHINFO_DIRNAME);
    $this->urlthumbdir=($this->prot?"https://":"http://").$_SERVER["SERVER_NAME"].$this->dname."/";
    $this->urldatadir=($this->prot?"https://":"http://").$_SERVER["SERVER_NAME"].$this->dname."/".$this->datadir;
    $this->urlthumbimg=$this->urlthumbdir.$this->thumbname.".png";
    $this->urlthumbcss=$this->urlthumbdir.$this->thumbname.".css";
  }
  function redirect($timer,$url) {
    echo '<!doctype html><html><head>';
    echo '<meta charset="utf-8">';
    echo '<meta http-equiv="refresh" content="'.$timer.';'.$url.'">';
    echo '</head><body>wait...</body></body></html>';
  }
  function getPath($d,$t){
    return $this->datadir.$d.".".$t;
  }
  function getThumbPath($d,$t){
    return $this->datadir.$d."_t.".$t;
  }
  function getParams($l){
    $la=explode("<>",$l);
    if(count($la)>=5)
      return $la;
    else
      return array("","",0,0,0,"");
  }
  function listItem($cur) {
    if(!is_readable($this->filelist))
      touch($this->filelist);
    $result="";
    $fp=fopen("./stat.txt","r");
    flock($fp,LOCK_SH);
    $line=fgets($fp);
    $stat=json_decode($line,true);
    fclose($fp);

    $fp=fopen($this->filelist,"r");
    flock($fp,LOCK_SH);
    $cnt=0;
    $line=fgets($fp);
    list($date,$type,$lat,$lng,$tmp)=$this->getParams($line);
    $result.=
      "{\n \"lastpos\":{\"lat\":".$stat["lat"].",\"lng\":".$stat["lng"]."},\n"
      ." \"thumbnail\":{\"img\":\"$this->urlthumbimg\",\"css\":\"$this->urlthumbcss\"},\n"
      ."\"data\":[\n";
    while(!feof($fp)) {
      list($date,$type,$lat,$lng,$tmp)=$this->getParams($line);
      if($date=="")
        break;
        $result.="  {\"date\":\"".$date."\",\"type\":\"".$type."\","
        ."\"lat\":".floatval($lat).",\"lng\":".floatval($lng).","."\"tmp\":".floatval($tmp).","
        ."\"url\":\"".$this->urldatadir.$date.".".$type."\"}";
      ++$cnt;
      if($cnt>=$this->maxfiles)
        break;
      $line=fgets($fp);
      if($line=="")
        break;
      $result.=",\n";
    }
    $result.="\n ]\n}";
    flock($fp,LOCK_UN);
    fclose($fp);
    return $result;
  }
  function setstat(){
    $drive=0;
    if(isset($_POST['drive'])) $drive=$_POST['drive'];
    else if(isset($_GET['drive'])) $drive=$_GET['drive'];
    $pid="";
    if(isset($_POST['pid'])) $pid=$_POST['pid'];
    else if(isset($_GET['pid'])) $pid=$_GET['pid'];
    $lat=0;
    if(isset($_POST['lat'])) $lat=$_POST['lat'];
    else if(isset($_GET['lat'])) $lat=$_GET['lat'];
    $lng=0;
    if(isset($_POST['lng'])) $lng=$_POST['lng'];
    else if(isset($_GET['lng'])) $lng=$_GET['lng'];
    $tim=0;
    if(isset($_POST['time'])) $tim=$_POST['time'];
    else if(isset($_GET['time'])) $tim=$_GET['time'];
    $fp=@fopen("./stat.txt","w+") or die("stat error");
    flock($fp,LOCK_EX);
    $line="{\"drive\":".$drive.",\"pid\":\"".$pid."\",\"time\":".$tim.",\"lat\":".$lat.",\"lng\":".$lng."}";
    fwrite($fp,$line);
    fclose($fp);

    $stat=json_decode($line,true);
    echo 'setstat:';
    echo $line;
  }
  function getstat(){
    $fp=@fopen("./stat.txt","r") or die("stat error");
    flock($fp,LOCK_SH);
    $line=fgets($fp);
    fclose($fp);
    echo $line;
  }
  function init(){
    unlink($this->filelist);
    $fileName = $this->datadir."*.*";
    foreach( glob($fileName) as $val )
      unlink($val);
    unlink($thumbname.".png");
    unlink($thumbname.".css");
  }
  function addItem() {
    if(isset($_FILES['file'])){
      $tempname = $_FILES['file']['tmp_name'];
      $origname = $_FILES['file']['name'];
      $img_size = $_FILES['file']['size'];
    }
    else{
      echo "err1";
      return;
    }
    $lat = $_POST['lat'];
    $lng = $_POST['lng'];
    $tmp = $_POST['tmp'];
    $f=pathinfo($origname);
    if(!$tempname){
      echo "err2";
      return;
    }
    if(!isset($f['extension'])){
      echo "err3";
      return;
    }
    $ext = $f['extension'];
    $type = strtolower($ext);
    date_default_timezone_set('Asia/Tokyo');
    $date = date("YmdHis");
    $newpath = $this->getPath($date,$type);
    move_uploaded_file("$tempname","$newpath");
    if(!is_readable($this->filelist))
      touch($this->filelist);
    $thumbnail = new images_to_sprite("d",$this->urlthumbdir,"sprite",63,63);
    $thumbnail->add_image($newpath);
    $fp = @fopen($this->filelist,"r") or die("File list error");
    flock($fp,LOCK_EX);
    $line = "$date<>$type<>$lat<>$lng<>$tmp<>\n";
    $fpnew=fopen("./filelist.new","w");
    fwrite($fpnew,$line);
    $cnt=1;
    while(!feof($fp)){
      $line=fgets($fp);
      list($date,$type,$lat,$lng,$fname)=$this->getParams($line);
      if($date!="")
        $thumbnail->add_image($this->getPath($date,$type));
      fwrite($fpnew,$line);
      ++$cnt;
      if($cnt>=$this->maxfiles)
        break;
    }
    while(!feof($fp)){
      $line=fgets($fp);
      list($date,$type,$lat,$lng,$fname)=$this->getParams($line);
      if($date!=""){
        unlink($this->getPath($date,$type));
      }
    }
    fclose($fp);
    fclose($fpnew);
    unlink($this->filelist);
    rename("./filelist.new",$this->filelist);
    $thumbnail->create_sprite();
  }
  function error($mes) {
    echo $mes;
    exit;
  }
}

$c=new App;
#if($_SERVER["REQUEST_METHOD"] == "POST") {
  header("Access-Control-Allow-Origin: ".$c->allowedOrigin);
  $cmd=null;
  if(isset($_GET['cmd']))
    $cmd=$_GET['cmd'];
  else if(isset($_POST['cmd']))
    $cmd=$_POST['cmd'];
  if(!$cmd){
    header("Content-Type: application/json; charset=utf-8");
    echo $c->listItem(0);
  }
  else{
    switch($cmd){
    case 'add':
      $c->addItem();
      break;
    case 'init':
      $c->init();
      break;
    case 'setst':
      $c->setstat();
      break;
    case 'getst':
      header("Content-Type: application/json; charset=utf-8");
      echo $c->getstat();
      break;
    }
  }
#  echo $c->redirect(1,"./index.html");
#  exit;
#}
#header("Access-Control-Allow-Origin: ".$c->allowedOrigin);
?>
