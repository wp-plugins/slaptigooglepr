<?php
/*
Plugin Name: Google PageRank Tool
Plugin URI: http://slaptijack.com/projects/
Description: This plugin adds a Google PageRank (PR) column to your Manage Pages and Manage Posts administration panels.  This will give you the individual PageRank of each page / post on your site. Also includes site-wide PageRank in the top right hand corner of your WordPress Dashboard.
Version: 15.07
Author: Scott Hebert
Author URI: http://slaptijack.com
*/

class SlaptiGooglePR {
  /* The following functions are from PageRank Lookup v1.1 by HM2K (http://www.hm2k.com/projects/pagerank/).  These functions were developed based on the algorithm at http://pagerank.gamesaga.net/
  */
    
  //convert a string to a 32-bit integer
  function StrToNum($Str, $Check, $Magic) {
    $Int32Unit = 4294967296;  // 2^32

    $length = strlen($Str);
    for ($i = 0; $i < $length; $i++) {
      $Check *= $Magic; 	
      /* If the float is beyond the boundaries of integer (usually +/- 2.15e+9 = 2^31), 
         the result of converting to integer is undefined
         refer to http://www.php.net/manual/en/language.types.integer.php */
      if ($Check >= $Int32Unit) {
        $Check = ($Check - $Int32Unit * (int) ($Check / $Int32Unit));
        $Check = ($Check < -2147483648) ? ($Check + $Int32Unit) : $Check;
      }
      $Check += ord($Str{$i}); 
    }
    return $Check;
  }
  
  //genearate a hash for a url
  function HashURL($String) {
    $Check1 = SlaptiGooglePR::StrToNum($String, 0x1505, 0x21);
    $Check2 = SlaptiGooglePR::StrToNum($String, 0, 0x1003F);

    $Check1 >>= 2; 	
    $Check1 = (($Check1 >> 4) & 0x3FFFFC0 ) | ($Check1 & 0x3F);
    $Check1 = (($Check1 >> 4) & 0x3FFC00 ) | ($Check1 & 0x3FF);
    $Check1 = (($Check1 >> 4) & 0x3C000 ) | ($Check1 & 0x3FFF);	
	
    $T1 = (((($Check1 & 0x3C0) << 4) | ($Check1 & 0x3C)) <<2 ) | ($Check2 & 0xF0F );
    $T2 = (((($Check1 & 0xFFFFC000) << 4) | ($Check1 & 0x3C00)) << 0xA) | ($Check2 & 0xF0F0000 );
	
    return ($T1 | $T2);
  }

  //genearate a checksum for the hash string
  function CheckHash($Hashnum) {
    $CheckByte = 0;
    $Flag = 0;

    $HashStr = sprintf('%u', $Hashnum) ;
    $length = strlen($HashStr);
	
    for ($i = $length - 1;  $i >= 0;  $i --) {
      $Re = $HashStr{$i};
      if (1 === ($Flag % 2)) {              
        $Re += $Re;     
        $Re = (int)($Re / 10) + ($Re % 10);
      }
      $CheckByte += $Re;
      $Flag ++;	
    }

    $CheckByte %= 10;
    if (0 !== $CheckByte) {
      $CheckByte = 10 - $CheckByte;
      if (1 === ($Flag % 2) ) {
        if (1 === ($CheckByte % 2)) {
          $CheckByte += 9;
        }
        $CheckByte >>= 1;
      }
    }

    return '7'.$CheckByte.$HashStr;
  }

  //return the PageRank checksum hash
  function getch($url) { return SlaptiGooglePR::CheckHash(SlaptiGooglePR::HashURL($url)); }

  //return the PageRank figure
  function getpr($url) {
    $googlehost='toolbarqueries.google.com';
    $googleua='Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.0.6) Gecko/20060728 Firefox/1.5';
    $ch = SlaptiGooglePR::getch($url);
    $fp = fsockopen($googlehost, 80, $errno, $errstr, 30);
    if ($fp) {
      $out = "GET /tbr?client=navclient-auto&features=Rank&ch=$ch&q=info:$url HTTP/1.1\r\n";
      $out .= "User-Agent: $googleua\r\n";
      $out .= "Host: $googlehost\r\n";
      $out .= "Connection: Close\r\n\r\n";
      
      fwrite($fp, $out);
      
      while (!feof($fp)) {
        $data = fgets($fp, 128);
        $pos = strpos($data, "Rank_");
        if($pos === false){} else{
          $pr=substr($data, $pos + 9);
          $pr=trim($pr);
          $pr=str_replace("\n",'',$pr);
          return $pr;
        }
      }
      fclose($fp);
    }
  }
  
  //generate the graphical PageRank
  function pagerank($url,$width=40,$method='style') {
    if (!preg_match('/^(http:\/\/)?([^\/]+)/i', $url)) { $url='http://'.$url; }
    $pr=SlaptiGooglePR::getpr($url);
    $pagerank="PageRank: $pr/10";

    //The (old) image method
    if ($method == 'image') {
      $prpos=$width*$pr/10;
      $prneg=$width-$prpos;
      $html='<img src="http://www.google.com/images/pos.gif" width='.$prpos.' height=4 border=0 alt="'.$pagerank.'"><img src="http://www.google.com/images/neg.gif" width='.$prneg.' height=4 border=0 alt="'.$pagerank.'">';
    }
    //The pre-styled method
    if ($method == 'style') {
      $prpercent  = 100 * $pr / 10;
      $html       = '<div style="position: relative; width: '.$width.'px; padding: 0; background: #D9D9D9;"><strong style="width: '.$prpercent.'%; display: block; position: relative; background: #5EAA5E; text-align: center; color: #333; height: 4px; line-height: 4px;"><span></span></strong></div>';
    }
	
    $out='<a href="'.$url.'" title="'.$pagerank.'">'.$html.'</a>';
    return $out;
  }
  
  /* The functions that interact with Wordpress. */
  function add_admin_css() {
    echo "
      <style type='text/css'>
      #slaptigooglepr_header {
        position: absolute;
        top: 4.5em;
        margin: 0; padding: 0;
        right: 215px;
        font-size: 11px;
      }
      .column-slaptigooglepr {
        text-align: center;
      }
      </style>";
  }
  
  function add_admin_footer() {
    $url = get_option('siteurl');
    $pr = 0 + SlaptiGooglePR::getpr($url);
    echo "<p id='slaptigooglepr_header'>Google PageRank: $pr</p>";
  }

  function add_manage_pages_column($pages_columns) {
    $pages_columns['slaptigooglepr'] = __('<span title=\'Provided by Google PageRank Tool\'>Google PR</span>', 'slaptigooglepr');
    return $pages_columns;
  }
  
  function add_manage_posts_column($posts_columns) {
    $posts_columns['slaptigooglepr'] = __('<span title=\'Provided by Google PageRank Tool\'>Google PR</span>', 'slaptigooglepr');
    return $posts_columns;
  }

  function display_manage_pages_column($colname, $id) {
    if ($colname != 'slaptigooglepr') { return; }
    
    $post = get_post($id);
    
    if ( $post->post_status != 'publish' ) {
      $pr = "N/A";
    } else {
      $url = get_permalink($id);
      $pr = 0 + SlaptiGooglePR::getpr($url);
    }
    
    echo "$pr";
  }
  
  function display_manage_posts_column($colname, $id) {
    if ($colname != 'slaptigooglepr') { return; }
    
    $post = get_post($id);
    
    if ( $post->post_status != 'publish' ) {
      $pr = "N/A";
    } else {
      $url = get_permalink($id);
      $pr = 0 + SlaptiGooglePR::getpr($url);
    }
    
    echo "$pr";
  }

    function rightnow() {
        $pr = 0 + SlaptiGooglePR::getpr(get_option('siteurl'));
        echo "<p id='slaptigooglepr'>Google PageRank: <strong>$pr</strong> <a title=\"Provided by Google PageRank Tool\" href=\"http://wordpress.org/extend/plugins/slaptigooglepr/\">[*]</a></p>";
    }
}


add_filter('manage_pages_columns', array('SlaptiGooglePR','add_manage_pages_column'));
add_filter('manage_posts_columns', array('SlaptiGooglePR','add_manage_posts_column'));
add_action('manage_pages_custom_column', array('SlaptiGooglePR','display_manage_pages_column'), 10, 2);
add_action('manage_posts_custom_column', array('SlaptiGooglePR','display_manage_posts_column'), 10, 2);

// I used to worry about breaking older versions of Wordpress and
// worked around the old stuff. The fact of the matter is if you're
// using an old enough version of Wordpress that the old workaround
// was a good thing, you're a menace to the Internet.
add_action('activity_box_end', array('SlaptiGooglePR','rightnow'));

?>
