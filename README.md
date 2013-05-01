PHP .cab archive Reader
-----------------------

>"Access Microsoft cabinet archives (.cab) in PHP.

**author**:      Lorenz Lo Sauer (c) 2001  
**website**:     https://lsauer.github.com/php-cab-reader  
**license**:     LGPL, BSD license  
**description**: Access and read Microsoft .cab archives  
**note**:        Only archived, uncompressed cab-archives can be read at the moment.   
**see**: http://msdn.microsoft.com/en-us/library/bb417343.aspx, "*Microsoft Cabinet Format*", 1997 Microsoft

## Installation
    require_once "cabreader.php"
    
## Example usage

    //included examplary .cab file
    $cabfile = "mod_apache.cab";
    
    $archiver = new cabarchive($cabfile);
    echo $archiver->get_filelist("html");

View the resulting output at: http://goo.gl/CfxSO

## API
<b>

    class cabarchive(filename)
    
        public file_read(filename)
        public get_filenames(filedata)
        public get_fileformat(filedata, "size"|"type" )
        public get_fileattributes(string-attributes)
        public binhex(filedata)
        public get_filelist(outformat, filename)
        public get_filedetails(filedata)
        public get_filedatetime(filedate, filetime)

</b>

- ### file_read
> Reads a cab-archive and checks the header, as well as compression. Returns a file handle or Errorstring `0Error:...`

    ```php
    $data = $archiver->file_read("mod_apache.cab");
    ```

- ### get_filenames
> Retrieves the filenames from a passed binary archive-stream. Returns an  array containing all filenames within the archive as strings.

    ```php
    $archiver->get_filenames($data);
    ```

- ### get_fileformat
> Formats a given file-size into a reduced string representation.

    ```php
    $fsize = strlen($data);
    $file_size,"size"
    $formatted_filesize = $archiver->get_fileformat($file_size,"size" );
    //> 301kB
    ```

- ### get_fileattributes
> Formats file-attributes from the cab-datastream and converts them to conventional MS file-attribute norm: e.g. `ahrx`

    ```php
    $substrdata = substr($data, 14, 2);
    $string_attribs = $archiver->get_fileattributes(
                                     $archiver->binhex($substrdata));
    ```

- ### binhex
> Converts the byte layout / byte swap in accordance with the MS cab Endianness

    ```php
    $bytes = $archiver->binhex($substrdata)
    ```

- ### get_filelist
> Returns a filelist by default in HTML format, otherwise as text. An optional second parameter allows setting another filename for access (*or archive path: not implemented*).

    ```php
    $htmldata = $archiver->get_filelist("html");
    //Tidy: PHP >4
    $tidy = tidy_parse_string($htmldata);
    $htmldata = $tidy->cleanRepair();
    file_put_contents("cab-filelist_$cabfile.html", $htmldata);
    ```

- ### get_filedetails
> When passed an filedata-frame of a cab-archive, `get_filedetails` returns an array: `array($file_size, $file_offset, $file_iFolder, $file_date, $file_time, $file_attribs, $file_name)`

    ```php
    $filedetails = $archiver->get_filedetails($substrdata);
    print_r($filedetails);
    ```

- ### get_filedatetime
> Formats and converts the `file_date` and `file_time` returned by the method `get_filedetails` into an array containing: `array($year, $month, $day, $hour, $min, $sec)`

    ```php
    list($year, $month, $day, $hour, $min, $sec)
       = $archiver->get_filedatetime($file_date, $file_time);
    ```