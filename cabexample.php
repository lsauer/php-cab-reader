<?php	
//example file for cab-archiver
//author: Lorenz Lo Sauer; lsauer.com, 2013
//license: public domain

require_once "cabreader.php";
$cabfile = "mod_apache.cab";

$archiver = new cabarchive($cabfile);

?>
<html>
<head >
<title>PHP .cab archive reader output for: mod_apache.cab</title>
  <script src="res/js/jquery-1.6.2.min.js"></script>
  <script src="res/js/jquery.dataTables.min.js"></script>
	<link type="text/css" href="res/css/demo_table.css" rel="stylesheet" />
<style>thead { font:bold 14px helvetica; cursor:pointer; }</style>
</head>
<body onLoad="$('#tblResult').dataTable({'bJQueryUI': true,'sPaginationType': 'full_numbers'});">
<?php
echo $archiver->get_filelist();
?>
<script>

</script>
</body>
</html>