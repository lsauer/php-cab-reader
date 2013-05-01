<?php
//author:       Lorenz Lo Sauer, 2001
//title:        A PHP based Microsoft Cabinet Format Reader
//license:      LGPL, BSD
//description:  Access and read Microsoft .cab archives
//note:         only archived, uncompressed cab-archives can be read at the moment


/*
 * ==Overview of the binary layout==
 *
Cab-Archive Binary layout:
See: "Microsoft Cabinet Format" http://msdn.microsoft.com/en-us/library/bb417343.aspx, 1997 Microsoft
                                            //     Offset	Description

                            //00..23		CFHEADER

$header .= "\x4D\x53\x43\x46"; 				//	00..03 signature MFCS
$header .= "\x00\x00\x00\x00";				//	04..07	reserved1
$header .= "$cbCabinet";				//	08..0B	cbCabinet = File Size in Bytes
$header .= "\x00\x00\x00\x00";				//	0C..0F	reserved2
$header .= "\x2C\x00\x00\x00";				//	10..13	coffFiles = Absolute file offset of first CFFILE entry.
$header .= "\x00\x00\x00\x00";				//	14..17	reserved3
$header .= "\x03\x01";					//	18..19	versionMinor, Major = 1.3
$header .= "$folders";					//	1A..1B	cFolders = The number of CFFOLDER entries in this cabinet file.
$header .= "$files";					//	1C..1D	cFiles = The number of CFFILE entries in this cabinet file.
$header .= "\x00\x00";					//	1E..1F	flags = 0 (no reserve, no previous or next cabinet)
$header .= "$setID";					//	20..21	setID = 0x0622
$header .= "\x00\x00";					//	22..23	iCabinet = 0

                            //24..2B		CFFOLDER[0]

$header .= "$coffCabStart";				//	24..27	coffCabStart = Absolute file offset of first CFDATA block for this folder.
$header .= "\x01\x00";					//	28..29	cCFData = Number of CFDATA structures for this folder that are actually in this cabinet
$header .= "\x00\x00";					//	2A..2B	typeCompress = 0 (none)

foreach($flist as $file){
                            //2C..43		CFFILE[0]
$header .= "$filesize[$file]";				//	2C..2F	cbFile = Uncompressed size of this file in bytes.
$header .= "$fileoffset[$file]";			//	30..33	uoffFolderStart = Uncompressed byte offset (first file = 0) of the start of this file's data.
$header .= "\x00\x00";					//	34..35	iFolder = Index of the folder containing this file's data.
$header .= "$filedate[$file]";				//	36..37	date = 0x226C = 0010001 0011 01100 = March 12, 1997 = Date of this file, in the format ((year-1980) << 9)+(month << 5)+(day)
$header .= "$filetime[$file]";				//	38..39	time = 0x59BA = 01011 001101 11010 = 11:13:52 AM = Time of this file, in the format (hour << 11)+(minute << 5)+(seconds/2)
$header .= "$fileattrib[$file]";			//	3A..3B	attribs = 0x0020 = _A_ARCHIVE
$header .= "$file\x00";					//	3C..43	szName = "hello.c" + NULL
}
                            //5E..FD		CFDATA[0]
$header .= "\x00\x00\x00\x00";				//	5E..61	csum = If is set to 0x00 then there is no csum check
$header .= "$cbData";					//	62..63	cbData = Number of bytes of compressed data in this CFDATA record.
//$header .=						//	64..65	cbUncomp = The uncompressed size of the data in this CFDATA entry
$header .= "\x97\x00";					//	66..FD	ab[0x0097] = uncompressed file data
*/

//HTTP GET variables: id, extract, folder


//Todo: function to read the determined data-range of a given file
/*
 * Rudimentary writing algorithm
 * 1. read out fileslist
 * 2. concatenate the data in a string
 * 3. determine the byte-length
 * 4. create a header via information obtained in 3.
 * 5. store data in gz-form
 * */


/**
 * Class cabarchive
 */
class cabarchive {

    /**
     * @var string
     */
    private $_filename;
    private $_html_tablehead = "
        <table border=0 cellspacing=0 cellpadding=0 width=718 height=30>
        <thead>
          <tr bgcolor=#D6D3CE>
            <td height=18>Filename:</td>
            <td height=18>Filesize:</td>
            <td height=18>Number of Files:</td>
            <td height=18>Number of Folders:</td>
            <td height=18>Multivolume:</td>
            <td height=18>ID:</td>
            <td height=18>Cab v.:</td>
          </tr>
         </thead>
          <tr>
            <td>{{filename}}</td>
            <td>{{filesize}}</td>
            <td>{{files}}</td>
            <td>{{folders}}</td>
            <td>{{multivolume}}</td>
            <td>{{setID}}</td>
            <td>{{version}}</td>
          </tr>
        </table><br>
        <table border=0 cellspacing=0 cellpadding=0 width=718 height=72 id=tblResult>
        <thead>
          <tr bgcolor=#D6D3CE>
            <td height=18>Filename</td>
            <td height=18>Filesize</td>
            <td height=18>Type</td>
            <td height=18>Modified</td>
            <td height=18>Attribute</td>
            <td height=18>Created: Date</td>
            <td height=18>Time</td>
            <td height=18>Compression</td>
          </tr>
          </thead>";

    function __construct($filename){
        $this->_filename = $filename;

    }

    /**
     * @param $file
     * @return string
     */
    function file_read($file){
        if(file_exists($file) && (is_file($file))){
            $fd = fopen($file, "r");
            $archive = fread ($fd, filesize ($file));
            fclose ($fd);
        }
        else return "0Error: File($file) doesn't exist";

        if(substr($archive,0,4) == "MSCF"){
            if( hexdec($this->binhex(substr($archive,8,4))) == strlen($archive))
                return $archive;
            else return "0Error: Wrong filesize ". strlen($archive);
        }
        else return "0Error: File is no cab";
    }

    //retrieves the filenames from a passed binary archive-stream
    /**
     * @param $file
     * @return array
     */
    function get_filenames($file){
        //  \x00 filename \x00
        //Todo: check and handle compressed archives

        //16 byte per file + null +filename +null
        #define _A_RDONLY      (0x01)	/* file is read-only */
        #define _A_HIDDEN      (0x02)	/* file is hidden */
        #define _A_SYSTEM      (0x04)	/* file is a system file */
        #define _A_ARCH        (0x20)	/* file modified since last backup */
        #define _A_EXEC        (0x40)	/* run after extraction */
        #define _A_NAME_IS_UTF (0x80)	/* szName[] contains UTF */
        $attribs = array("01","02","03","04","05","06","07","20","20","21","22",
            "23","24","25","26","27","40","41","42","43","44","45",
            "46","47","60","61","62","63","64","65","66","67","80",
            "81","82","83","84","85","86","87");
        $filenames = Array();
        $tmp = null;

        for ($i=0;$i <= strlen($file);$i++){
            if (bin2hex($file[$i]) == "00"){
                //check the fileattribute
                foreach($attribs as $attrib){
                    if(bin2hex($file[$i-1]) == "$attrib"){
                        //check for a filename
                        $x = 1;
                        while(preg_match("/[[:alnum:]._ -]/",$file[$i+$x],$reg)){

                            $tmp .= $reg[0];
                            //check if a new filename was found (the next element starts with '0x00') and set the datapointer forward +16by
                            if( bin2hex($file[$i+$x+1]) == "00"){
                                $filenames[] = $tmp;
                                $i+= strlen($tmp)+16;
                                //echo "New entry: $tmp at $i<br>";
                                $tmp = null;
                                break 2;
                            }
                            $x++;
                        }
                    }
                }
            }
        }
        return $filenames;
    }

    /**
     * @param $string
     * @param $type
     * @param $add
     * @return string
     */
    function get_fileformat ($string, $type){
        $origstring = $string;

        if($type == "size"){
            $file_size = $string;

            //$string = chunk_split($string,$len,".");
            if($file_size < 1000)
                $file_size_show = "$file_size Bytes";
            elseif(($file_size >= 1000) && ($file_size < 1000000)){
                $file_size = round($file_size / 1000);
                $file_size_show = "$file_size kB";

            }
            elseif(($file_size >= 1000000) && ($file_size < 1000000000)) {
                $file_size = round($file_size / 1000000);
                $file_size_show = "$file_size MB";
            }
            elseif(($file_size >= 1000000000) && ($file_size < 1000000000000)) {
                $file_size = round($file_size / 1000000);
                $file_size_show = "$file_size GB";
            }

            $string = $file_size_show;
        }

        else if($type == "type"){
            $file_type = $string;
            $file_type = explode(".", $file_type);

            if(count($file_type) > 1)
                $file_type = $file_type[count($file_type)-1];
            else
                $file_type = " - ";

            $string = $file_type;
        }

        //if($origstring == $string)
        //	return "No modification was processed at string: \"$string\"";
        //elseif(!$string)
        //	return false;
        //else
        return $string;
    }

    /**
     * @param $attrib
     * @return string
     */
    function get_fileattributes($attrib){
        //write cleaner code!!!

        #define _A_RDONLY      (0x01)	/* file is read-only */
        #define _A_HIDDEN      (0x02)	/* file is hidden */
        #define _A_SYSTEM      (0x04)	/* file is a system file */
        #define _A_ARCH        (0x20)	/* file modified since last backup */
        #define _A_EXEC        (0x40)	/* run after extraction */
        #define _A_NAME_IS_UTF (0x80)	/* szName[] contains UTF */

        $arcn = ($attrib[2] == 2) ? 'a' : '';	/* set archive bit */
        $arch = ($attrib[2] == 6) ? 'ax' : '';	/* set archive bit */
        $exec = ($attrib[2] == 4) ? 'x' : '';	/* set exec bit */
        $utf =  ($attrib[2] == 8) ? 'c' : '';	/* set UTF bit */

        if($arch == '')
            $arch = $arcn;
        if(strlen("$arch$exec$utf") == 0)
            $arch = '0';

        // Determine Type
        if( $attrib[3] == 1 )
            $type = $arch.$exec.$utf."r"; 				/* file is read-only */
        else if( $attrib[3] == 2 )
            $type = $arch.$exec.$utf."h";	 			/* file is hidden */
        else if( $attrib[3] == 3 )
            $type = $arch.$exec.$utf."rh"; 				/* file is read-only & hidden */
        else if( $attrib[3] == 4 )
            $type = $arch.$exec.$utf."s"; 				/* file is system*/
        else if( $attrib[3] == 5 )
            $type = $arch.$exec.$utf."rs"; 				/* file is read-only & system */
        else if( $attrib[3] == 6 )
            $type = $arch.$exec.$utf."hs"; 				/* file is hidden & system*/
        else if( $attrib[3] == 7 )
            $type = $arch.$exec.$utf."rhs"; 				/* file is read-only, hidden & system */
        else
            $type = $arch.$exec.$utf; 		/* UNKNOWN */

        $mode = null;
        $mode .= ( preg_match("/[r]+/",$type) ) ? 'r' : '-';
        $mode .= ( preg_match("/[a]+/",$type) ) ? 'a' : '-';
        $mode .= ( preg_match("/[h]+/",$type) ) ? 'h' : '-';
        $mode .= ( preg_match("/[s]+/",$type) ) ? 's' : '-';
        $mode .= ( preg_match("/[x]+/",$type) ) ? 'x' : '-';
        $mode .= ( preg_match("/[c]+/",$type) ) ? 'c' : '-';

        //if( stristr ($HTTP_USER_AGENT,"Linux"))
        //	return 	0x20;
        //else
        return $mode;
    }

    //convert byte layout / byte swap
    /**
     * @param $data
     * @return null|string
     */
    function binhex($data){
        $new = null;
        $data = bin2hex($data);
        $len = strlen($data);
        for($i=0;$i < $len;$i+=2){
            $new .= $data[$len-$i-2];
            $new .= $data[$len-$i-1];
        }
        return $new;
    }

    /**
     * @param $file2open
     */
    function get_filelist($outformat="html", $filename=null){
        $off = 0;
        $file_show = '';
        $file = $this->file_read($filename ? $filename : $this->_filename);
        if($file[0] == "0") {
            echo substr($file, 1, strlen($file)-1);
        }elseif($file) {

            //assumption: Only one CFFolder => COFFCabStart...Byteposition (00) of the last filename
            $coffCabStart = hexdec($this->binhex(substr($file,36,4)));
            //echo $coffCabStart;
            $header = substr($file,0,$coffCabStart);
            $data = substr($file,$coffCabStart,8);	//4+2+2
            $content = substr($file,$coffCabStart+8,(strlen($file)-$coffCabStart-8));

            $filesize_archive = strlen($file);

            $coffFiles =hexdec($this->binhex(substr($header,16,4)));
            $version =  hexdec($this->binhex($header[25])).".".hexdec($this->binhex($header[24]));
            $folders =  hexdec($this->binhex(substr($header,26,2)));
            $files =    hexdec($this->binhex(substr($header,28,2)));
            $flags =    hexdec($this->binhex(substr($header,30,2))); //unused
            $setID =    hexdec($this->binhex(substr($header,32,2)));
            $iCabinet = hexdec($this->binhex(substr($header,34,2))); //multi volume

            $files_header = substr($header,$coffFiles,($coffCabStart-$coffFiles));

            $filenames = $this->get_filenames($files_header);
            //print $filenames;
            //always: 16 byte +Bnull filename +Bnull
            //every: 18bytes there is thus a new file-entry

            foreach ($filenames as $filename){
                $fheader[$filename] = substr($files_header,$off,17 + strlen($filename));
                //echo "offset von $filename ist: $off<br><br>$fheader[$filename]<br><br>";
                $off += 17 + strlen($filename);
            }

            $multivolume = ($iCabinet) ? "Yes" : "No";

            $outstring = preg_replace(array('/\{\{filename\}\}/', '/\{\{filesize\}\}/', '/\{\{files\}\}/', '/\{\{folders\}\}/', '/\{\{multivolume\}\}/', '/\{\{setID\}\}/', '/\{\{version\}\}/'),
                         array($this->_filename,$filesize_archive,$files, $folders, $multivolume,$setID,$version), $this->_html_tablehead);

            $file_show .= $outstring;

            $cnt = 0;
            foreach($fheader as $file_header){

                list($file_size, $file_offset, $file_iFolder, $file_date, $file_time, $file_attribs, $file_name) = $this->get_filedetails($file_header);
                list($year, $month, $day, $hour, $min, $sec) = $this->get_filedatetime($file_date, $file_time);


                //unfortunately only the uncompressed filesize of the previous file can be determined:
                //$file_size_compr = abs($file_offset[$i-1] - $file_offset[$i]);
                //echo strlen(substr($content,$file_offset[$i],$offset))."<br><br><br>";
                //if($i)
                //  $compression = ( $file_size_compr / $file_size);

                $file_size_compr = "0% (stored)";

                $file_size_show = $this->get_fileformat($file_size,"size" );
                $file_type = $this->get_fileformat($file_name,"type" );

                $file_show .= "
                  <tr>
                    <td><a href=cabread.php?extract=$file_name&id=$cnt&folder=$file_iFolder>$file_name</a></td>
                    <td>$file_size_show</td>
                    <td>$file_type</td>
                    <td>&nbsp;</td>
                    <td>$file_attribs</td>
                    <td>$day.$month.$year</td>
                    <td>$hour:$min:$sec</td>
                    <td>$file_size_compr</td>
                  </tr>";
                $cnt++;
            }

            $file_show .= "</table>";
        }//end:  elseif($file)

        //switch formatting to simple text
        if($outformat != "html"){
            $file_show = preg_replace(array('/<tr.*?>/gm', '/<.*?>/gm'), array("\n",' '), $file_show);
        }
        //handle url GET operation
        if( isset($_GET['id'])){
            $id = $_GET['id'];
            if($_GET['extract'] && $_GET['folder'] >= 0 && $id >=0)
            {
                $show = substr($content,$file_offset[$id],$file_offset[$id+1] - $file_offset[$id]);
                return $file_offset[$id+1] - $file_offset[$id].$show ;
            }
        }else {
            return $file_show;
        }
    }

    /**
     * @param $file_header
     * @param $file_offset
     * @return array
     */
    public function get_filedetails($file_header)
    {
        $file_offset = Array();

        //File header - data range:
        //  2C..43		CFFILE[0]

        //	2C..2F	cbFile = Uncompressed size of this file in bytes.
        $file_size = hexdec($this->binhex(substr($file_header, 0, 4)));
        //	30..33	uoffFolderStart = Uncompressed byte offset (first file = 0) of the start of this file's data.
        $file_offset[] = hexdec($this->binhex(substr($file_header, 4, 4)));
        //	34..35	iFolder = Index of the folder containing this file's data.
        $file_iFolder = hexdec($this->binhex(substr($file_header, 8, 2)));
        //	36..37	date = 0x226C = 0010001 0011 01100 = March 12, 1997 = Date of this file, in the format ((year-1980) << 9)+(month << 5)+(day)
        $file_date = decbin(hexdec($this->binhex(substr($file_header, 10, 2))));
        //	38..39	time = 0x59BA = 01011 001101 11010 = 11:13:52 AM = Time of this file, in the format (hour << 11)+(minute << 5)+(seconds/2)
        $file_time = decbin(hexdec($this->binhex(substr($file_header, 12, 2))));
        //	3A..3B	attribs = 0x0020 = _A_ARCHIVE
        $file_attribs = $this->get_fileattributes($this->binhex(substr($file_header, 14, 2)));
        //	3C..43	szName = "hello.c" + NULL
        $file_name = substr($file_header, 16, strlen($file_header) - 16);
        return array($file_size, $file_offset, $file_iFolder, $file_date, $file_time, $file_attribs, $file_name);
    }

    /**
     * @param $file_date
     * @param $file_time
     * @return array
     */
    public function get_filedatetime($file_date, $file_time)
    {
        //format remaining fields such as filedate/time...
        $file_date = str_pad($file_date, 16, "0", STR_PAD_LEFT);
        $file_time = str_pad($file_time, 16, "0", STR_PAD_LEFT);

        $year = bindec(substr($file_date, 0, 7));
        $year = "$year";
        if ($year[0] == 2)
            $year = "$year[0]00$year[1]";
        elseif ($year[0] == 1)
            $year = "$year[0]99$year[1]";
        $month = bindec(substr($file_date, 7, 4));
        $day = bindec(substr($file_date, 11, 5));
        $hour = bindec(substr($file_time, 0, 5));
        $min = bindec(substr($file_time, 5, 6));
        $sec = bindec(substr($file_time, 11, 5)) * 2;
        return array($year, $month, $day, $hour, $min, $sec);
    }


}//End of class cabarchive
?>
