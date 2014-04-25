<?php

# gpl2
# by crutchy
# 25-april-2014

define("CHAN_CIV","#civ");
define("NICK","exec");

ini_set("display_errors","on");

$cmd=$argv[1];
$trailing=$argv[2];
$data=$argv[3];
$dest=$argv[4];
$params=$argv[5];
$nick=$argv[6];

/*
:crutchy!~crutchy_@724-640-25-593.cust.aussiebb.net NICK :crutchy|blah
:crutchy|blah!~crutchy_@724-640-25-593.cust.aussiebb.net NICK :crutchy
*/

switch ($cmd)
{
  case "330": # is logged in as
    $parts=explode(" ",$params);
    if ((count($parts)==3) and ($parts[0]==NICK))
    {
      $nick=$parts[1];
      $account=$parts[2];
      echo ":".NICK." NOTICE ".CHAN_CIV." :civ login $nick $account\n";
    }
    break;
  case "JOIN":
    echo "IRC_RAW WHOIS $nick\n";
    break;
  case "PART":
    echo ":".NICK." NOTICE ".CHAN_CIV." :civ logout $nick\n";
    break;
  case "NICK":
    echo ":".NICK." NOTICE ".CHAN_CIV." :civ rename $nick $trailing\n";
    break;
  case "PRIVMSG":
    break;
  case "NOTICE":
    break;
}

?>