<?php

# gpl2
# by crutchy
# 14-april-2014

# weather request, Spirit Of Saint Louis, Missouri (38.7°N/90.7°W), Updated: 1:54 PM CST (December 23, 2013), Conditions: Mostly Cloudy, Temperature: 26°F (-3.3°C), Windchill: 16°F (-9°C), High/Low: 26/9°F (-3.3/-12.8°C), UV: 1/16, Humidity: 66%, Dew Point: 16°F (-8.9°C), Pressure: 30.51 in/1033 hPa, Wind: WNW at 10 MPH (17 KPH)

# http://wxqa.com/APRSWXNETStation.txt
# EW4841|E4841|EW4841 Murrumbena                    AU|45|  -37.90783|145.07217|GMT|||1||||
# http://www.wxqa.com/cgi-bin/search1.cgi?keyword=EW4841
# http://www.findu.com/cgi-bin/wx.cgi?call=EW4841&units=metric

# http://www.worldweather.org/
# http://www.wmo.int/pages/prog/www/index_en.html
# http://www.wmo.int/pages/prog/www/ois/ois-home.html

# TODO: registered nick personalised settings (units, default location, private msg, formatting, etc)
# TODO: delete codes

# TODO: get registered nicks through mother, pass privmsgs and termmsgs to mother, and remove all irc-related stuff

$pwd=file_get_contents("weather.pwd");
define("NICK","weather");
define("PASSWORD",$pwd);
unset($pwd);
define("LOG_FILE","weather.log");
define("CODES_FILE","weather.codes");
define("CMD_QUIT","~quit");
define("CMD_WEATHER","weather");
define("CMD_ADDCODE","weather-add");
define("CHAN_LIST","#test,##,#soylent");
#define("CHAN_LIST","#test");
define("SEDBOT_EXCLUDE_PREFIX","for ");
set_time_limit(0);
ini_set("display_errors","on");
$codes=unserialize(file_get_contents(CODES_FILE));
$fp=fsockopen("irc.sylnt.us",6667);
fputs($fp,"NICK ".NICK."\n");
fputs($fp,"USER ".NICK." * ".NICK." :".NICK."\n");
while (feof($fp)===False)
{
  $data=fgets($fp);
  if ($data===False)
  {
    continue;
  }
  if (pingpong($fp,$data)==True)
  {
    continue;
  }
  echo $data;
  $items=parse_data($data);
  if ($items!==False)
  {
    append_log($items);
    $params=explode(" ",$items["msg"]);
    switch (strtolower($params[0]))
    {
      case CMD_QUIT:
        if ($items["nick"]=="crutchy")
        {
          fputs($fp,": QUIT\n");
          fclose($fp);
          term_echo("QUITTING SCRIPT");
          return;
        }
        break;
      case CMD_ADDCODE:
        if (count($params)>2)
        {
          $code=$params[1];
          unset($params[0]);
          unset($params[1]);
          $codes[$code]=trim(implode(" ",$params));
          if (file_put_contents(CODES_FILE,serialize($codes))===False)
          {
            privmsg($items["chan"],"code \"$code\" set for location \"".$codes[$code]."\" but there was an error writing the codes file");
          }
          else
          {
            privmsg($items["chan"],"code \"$code\" set for location \"".$codes[$code]."\"");
          }
        }
        break;
      case CMD_WEATHER:
        unset($params[0]);
        $location=trim(implode(" ",$params));
        if ($location<>"")
        {
          if (strtolower(substr($location,0,strlen(SEDBOT_EXCLUDE_PREFIX)))<>SEDBOT_EXCLUDE_PREFIX)
          {
            process_weather($location,$items["chan"]);
          }
        }
        else
        {
          privmsg($items["chan"],"SOYLENT IRC WEATHER INFORMATION BOT");
          privmsg($items["chan"],"  usage: \"weather location\" (visit http://wiki.soylentnews.org/wiki/IRC#weather for more info)");
          privmsg($items["chan"],"  data courtesy of the APRS Citizen Weather Observer Program (CWOP) @ http://weather.gladstonefamily.net/");
          privmsg($items["chan"],"  by crutchy: https://github.com/crutchy-/test/blob/master/weather.php");
        }
        break;
      default:
        {
        }
    }
  }
  if (strpos($data,"End of /MOTD command")!==False)
  {
    fputs($fp,"JOIN ".CHAN_LIST."\n");
  }
  if (strpos($data,"You have 60 seconds to identify to your nickname before it is changed.")!==False)
  {
    fputs($fp,"NICKSERV identify ".PASSWORD."\n");
  }
  usleep(10000); # 0.01 second
}

function pingpong($fp,$data)
{
  $parts=explode(" ",$data);
  if (count($parts)>1)
  {
    if ($parts[0]=="PING")
    {
      fputs($fp,"PONG ".$parts[1]."\n");
      return True;
    }
  }
  return False;
}

function append_log($items)
{
  $data=serialize($items);
  if ($data===False)
  {
    term_echo("Error serializing log items.");
    return;
  }
  if (file_put_contents(LOG_FILE,$data."\n",FILE_APPEND)===False)
  {
    term_echo("Error appending log file \"".LOG_FILE."\".");
  }
}

function term_echo($msg)
{
  echo "\033[1;31m$msg\033[0m\n";
}

function parse_data($data)
{
  # :nick!addr PRIVMSG chan :msg
  $result=array();
  if ($data=="")
  {
    return False;
  }
  if ($data[0]<>":")
  {
    return False;
  }
  $i=strpos($data," :");
  $result["msg"]=trim(substr($data,$i+2));
  if ($result["msg"]=="")
  {
    return False;
  }
  $sub=substr($data,1,$i-1);
  $i=strpos($sub,"!");
  $result["nick"]=substr($sub,0,$i);
  if (($result["nick"]=="") or ($result["nick"]==NICK))
  {
    return False;
  }
  $sub=substr($sub,$i+1);
  $i=strpos($sub," ");
  $result["addr"]=substr($sub,0,$i);
  if ($result["addr"]=="")
  {
    return False;
  }
  $sub=substr($sub,$i+1);
  $i=strpos($sub," ");
  $cmd=substr($sub,0,$i);
  if ($cmd<>"PRIVMSG")
  {
    return False;
  }
  $result["chan"]=substr($sub,$i+1);
  if ($result["chan"]=="")
  {
    return False;
  }
  $result["microtime"]=microtime(True);
  $result["time"]=date("Y-m-d H:i:s",$result["microtime"]);
  return $result;
}

function privmsg($chan,$msg)
{
  global $fp;
  if ($chan=="")
  {
    term_echo("Channel not specified.");
    return;
  }
  if ($msg=="")
  {
    term_echo("No text to send.");
    return;
  }
  fputs($fp,":".NICK." PRIVMSG $chan :$msg\r\n");
  term_echo($msg);
}

function wget($host,$uri,$port)
{
  $fp=fsockopen($host,$port);
  if ($fp===False)
  {
    term_echo("Error connecting to \"$host\".");
    return;
  }
  fwrite($fp,"GET $uri HTTP/1.0\r\nHost: $host\r\nConnection: Close\r\n\r\n");
  $response="";
  while (!feof($fp))
  {
    $response=$response.fgets($fp,1024);
  }
  fclose($fp);
  return $response;
}

function process_weather($location,$chan)
{
  global $codes;
  if (isset($codes[$location])==True)
  {
    $loc=$codes[$location];
  }
  else
  {
    $loc=$location;
  }
  # http://weather.gladstonefamily.net/site/search?site=melbourne&search=Search
  $search=wget("weather.gladstonefamily.net","/site/search?site=".urlencode($loc)."&search=Search",80);
  if (strpos($search,"Pick one of the following")===False)
  {
    privmsg($chan,"Weather for \"$loc\" not found. Check spelling or try another nearby location.");
    return;
  }
  $parts=explode("<li>",$search);
  $delim1="/site/";
  $delim2="\">";
  $delim3="</a>";
  for ($i=0;$i<count($parts);$i++)
  {
    if ((strpos($parts[$i],"/site/")!==False) and (strpos($parts[$i],"[no data]")===False) and (strpos($parts[$i],"[inactive]")===False))
    {
      term_echo($parts[$i]);
      $j1=strpos($parts[$i],$delim1);
      $j2=strpos($parts[$i],$delim2);
      $j3=strpos($parts[$i],$delim3);
      if (($j1!==False) and ($j2!==False) and ($j3!==False))
      {
        $name=substr($parts[$i],$j2+strlen($delim2),$j3-$j2-strlen($delim2));
        $station=substr($parts[$i],$j1+strlen($delim1),$j2-$j1-strlen($delim1));
        # http://weather.gladstonefamily.net/cgi-bin/wxobservations.pl?site=94868&days=7
        $csv=trim(wget("weather.gladstonefamily.net","/cgi-bin/wxobservations.pl?site=".urlencode($station)."&days=3",80));
        $lines=explode("\n",$csv);
        # UTC baro-mb temp°F dewpoint°F rel-humidity-% wind-mph wind-deg
        # 2014-04-07 17:00:00,1020.01,54.1,53.6,98,0,0,,,,,,
        $first=$lines[count($lines)-2];
        $last=$lines[count($lines)-1];
        term_echo($last);
        $data_first=explode(",",$first);
        $data_last=explode(",",$last);
        if (($data_last[1]=="") or ($data_last[2]=="") or (count($data_first)<7) or (count($data_last)<7))
        {
          continue;
        }
        $dt=0;
        $age=-1;
        if (($data_first[0]<>"") and ($data_last[0]<>""))
        {
          # 2014-04-12 23:00:00
          $date_arr1=date_parse_from_format("Y-m-d H:i:s",$data_first[0]);
          $date_arr2=date_parse_from_format("Y-m-d H:i:s",$data_last[0]);
          $ts1=mktime($date_arr1["hour"],$date_arr1["minute"],$date_arr1["second"],$date_arr1["month"],$date_arr1["day"],$date_arr1["year"]);
          $ts2=mktime($date_arr2["hour"],$date_arr2["minute"],$date_arr2["second"],$date_arr2["month"],$date_arr2["day"],$date_arr2["year"]);
          $dt=round(($ts2-$ts1)/60/60,1);
          $utc_str=gmdate("M d Y H:i:s",time());
          $utc=strtotime($utc_str);
          $age=round(($utc-$ts2)/60/60,1);
        }
        if ($data_last[2]=="")
        {
          $temp="(no data)";
        }
        else
        {
          $tempF=round($data_last[2],1);
          $tempC=round(($tempF-32)*5/9,1);
          $temp=$tempF."°F (".$tempC."°C)";
        }
        if ($data_last[1]=="")
        {
          $press="(no data)";
        }
        else
        {
          $delta_str="";
          if (($dt>0) and ($data_first[1]<>""))
          {
            $d=round($data_first[1]-$data_last[1],1);
            $delta_str=" ~ change of $d mb over past $dt hrs"; # TODO: remove "past"
          }
          $pressmb=round($data_last[1],1);
          $press=$pressmb." mb".$delta_str;
        }
        if ($data_last[3]=="")
        {
          $dewpoint="(no data)";
        }
        else
        {
          $tempF=round($data_last[3],1);
          $tempC=round(($data_last[3]-32)*5/9,1);
          $dewpoint=$tempF."°F (".$tempC."°C)";
        }
        if ($data_last[3]=="")
        {
          $dewpoint="(no data)";
        }
        else
        {
          $tempF=round($data_last[3],1);
          $tempC=round(($tempF-32)*5/9,1);
          $dewpoint=$tempF."°F (".$tempC."°C)";
        }
        if ($data_last[4]=="")
        {
          $relhumidity="(no data)";
        }
        else
        {
          $relhumidity=round($data_last[4],1)."%";
        }
        if ($data_last[5]=="")
        {
          $wind_speed="(no data)";
        }
        else
        {
          $wind_speed_mph=round($data_last[5],1);
          $wind_speed_kph=round($data_last[5]*8/5,1);
          $wind_speed=$wind_speed_mph." mph (".$wind_speed_kph." km/h)";
        }
        if ($data_last[6]=="")
        {
          $wind_direction="(no data)";
        }
        else
        {
          $wind_direction=round($data_last[6],1)."°"; # include N/S/E/W/NE/SE/NW/SW/NNE/ENE/SSE/ESE/etc
        }
        $agestr=":";
        if ($age>=0)
        {
          $agestr=" ~ $age hrs ago:";
        }
        privmsg($chan,"Weather for $name at ".$data_last[0]." (UTC)$agestr");
        privmsg($chan,"    temperature = $temp    dewpoint = $dewpoint");
        privmsg($chan,"    barometric pressure = $press    relative humdity = $relhumidity");
        privmsg($chan,"    wind speed = $wind_speed    wind direction = $wind_direction");
        return;
      }
    }
  }
  privmsg($chan,"All stations matching \"$loc\" are either inactive or have no data. Check spelling or try another nearby location.");
}

?>
