<?php
/**
 * checkemailaddress.php
 *
 * php validation of email address
 * @author Jeff Hendrickson JKH <jeff@hendricom.com>
 * @version 1.0
 * @package Utilities
 */
 
/*
   // example:
   require_once 'checkemailaddress.php';
   
    $emailAddress = "jeff@hendricom.com";
    $helloDomain = "hendricom.com";
    
    printf("Checking " . $emailAddress . ", at " . $helloDomain . PHP_EOL);
    $result = Test($emailAddress, $helloDomain);
    if ($result) {
        printf($emailAddress . " valid!");
    } else {
        printf($emailAddress . " invalid!");
    }
 */ 

/*
$emailAddress = "jeff@hendricom.com";
$helloDomain = "hendricom.com";
*/

$emailAddress = "chris@skycoastal.com";
$helloDomain = "claimpros.com";

printf("Checking " . $emailAddress . ", at " . $helloDomain . PHP_EOL);
$result = Test($emailAddress, $helloDomain);
if ($result) {
    printf($emailAddress . " valid!" . PHP_EOL);
} else {
    printf($emailAddress . " invalid!" . PHP_EOL);
}
   
function Test($emailAddress, $helloDomain) {

    $result = CheckEmailAddress($emailAddress, $helloDomain);
    return $result;
  
}

// JKH added
function CheckEmailAddress($emailAddress, $helloDomain) {

    $bRetcode = false;
    
    printf("Validating " . $emailAddress . PHP_EOL);
    
    // JKH note: this is also in the other func.php
    $domain = substr($emailAddress, strpos($emailAddress, '@') + 1);
    // JKH this may be claims.allstate.com
    //
    $domainArray = explode('.', $domain);
    if (sizeof($domainArray) < 2) {
        printf("Invalid domain " . $domain . "!");
        return $bRetcode;
    }
    // remember 0 offset...
    $ext = $domainArray[sizeof($domainArray)-1];
    $tld = $domainArray[sizeof($domainArray)-2];
    $domain = $tld . "." . $ext;
    printf("Email domain " . $domain);

    // get the Name Server
    printf("dig " . $domain . " ns +short" . PHP_EOL);
    exec("dig " . $domain . " ns +short", $dns);
    if($dns == "") {
        printf("No DNS server returned!\n");;
        return $bRetcode;
    }
    $nameserver = "";
    $backupnameserver = "";
    foreach($dns as $key => $value) {
        $nameserver = $value;
        if (strlen($backupnameserver) == 0) {
            $backupnameserver = $value;
        }
    }

    // get the MXs for the domain using the domain's Name Server
    printf("dig @" . $nameserver . " +short MX " . $domain . PHP_EOL);
    exec("dig @" . $nameserver . " +short MX " . $domain, $mx);
    if (sizeof($mx) < 1) {
        // make a second attempt with the backupnameserver if present
        if (strlen($backupnameserver) > 0) {
            printf("dig @" . $backupnameserver . " +short MX " . $domain . PHP_EOL);
            exec("dig @" . $backupnameserver . " +short MX " . $domain, $mx);
        }
        // if we still haven't found the mx, bail out
        if (sizeof($mx) < 1) {
            printf("Error, " . $domain . " invalid!" . PHP_EOL);
            return $bRetcode;
        }
    }

    // we need a list of the mail exchanges to loop through
    // in case the first one doesn't work...
    $MXs = array();
    foreach($mx as $key => $value) {
        // note: this may be ...
        //  g.root-servers.net.
        //  f.root-servers.net.
        //  etc...
        // vice ...
        // 20 mx1.zoho.com.
        // 10 mx2.zoho.com.
        // note: MXs are rotated on their own, so no need to check top
        //  just use first value...
        if (preg_match('/(\d+) (.*)\./', $value, $output_array)) {
            $MXs[] = $output_array[2];
        } elseif (preg_match('/(.*)\./', $value, $output_array) ) {
            $MXs[] = $output_array[1];
        }
    }

    // JKH note: these can be returned as e.root-servers.net:25
    foreach ($MXs as $mx) {
        $mx_split = explode(":", $mx);
        $top_mx = $mx_split[0];
        $port = 25;
        if (sizeof($mx_split) == 2) {
            $port = $mx_split[1];
        }
        printf("Connecting " . $top_mx . PHP_EOL);
        // connect to MX for domain with a 15 second timeout
        $fp = @fsockopen($top_mx, $port, $errno, $errstr, 15);
        if ($fp) {
            break;
        }
    }

    if (!$fp) {
        printf($top_mx . " connect fails! " . "errstr = " . $errstr . PHP_EOL);
        return $bRetcode;
    } else {
       printf($top_mx . " connects.");
        $response = "";
        $i = 0;
        while (strlen($response) == 0) {
            printf("Read " . ++$i . PHP_EOL);
            // give it more than one go...
            // double checking fp in case it was re-opened as described below
            if ($fp) {
                $response = fgets($fp, 1024);
            }
            if (strlen($response) == 0) {
                if ($i > 3) {
                    printf("Aborting mx read" . PHP_EOL);
                    // three times and you're out
                    break;
                }
                // we're going to try this again
                fclose($fp);
                // give it a rest, and try again with a longer wait.
                usleep(100 * 100);
                $fp = @fsockopen($top_mx, 25, $errno, $errstr, 30);
            } else {
                // we've received our response
                break;
            }
        }

        printf("Response = " . $response);
        // sometimes the server is a relay, and you'll get two 220s e.g.
        // 220-mx1-us1.ppe-hosted.com - Please wait...
        // 220 mx1-us1.ppe-hosted.com - Welcome to PPE Hosted ESMTP Server
        $proceed = true;
        $tries = 3;
        while ($proceed && $tries-- > 0) {
            if (substr($response, 0, 3) != "220") {
                $error_number = substr($response, 0, 3);
                if ($error_number == 554) {
                    // how to report warning?
                    // this broken connect may be because of DNS RBL reports by IP
                    // Response 554 5.7.1 ACL dns_rbl; Client host [45.58.42.7] blocked using Spamhaus PBL
                    // if you've gotten this far, the domain is right, the mx is right, and I would give
                    // the email address a pass, a truly unroutable email would have failed by now...
                    printf("Error, " . $top_mx . " forcibly closes, fail!" . PHP_EOL);
                    return $bRetcode;
                }
                printf("Error, " . $top_mx . " fail!" . PHP_EOL);
                return $bRetcode;
            }
            $position = strripos($response, "wait");
            if ($position === false) {
                // we can break, else go for the next 220
                printf("SMTP Ok to proceed" . PHP_EOL);
                $proceed = false;
            } else {
                // there was a "wait" so lets get the next string
                $response = fgets($fp, 1024);
            }
        }
        $out = "HELO " . $helloDomain . "\r\n";
        printf("Sending HELO " . $out);
        fwrite($fp, $out);
        $response = fgets($fp, 1024);
        if (substr($response, 0, 3) != "250") {
            // some times a server will be acting as relay, e.g. mx1-us1.ppe-hosted.com
            // this will connect more slowly so we increased the timeout
            if (substr($response, 0, 3) == "521")  {
                printf("Warning: Server reports it is relay, continuing check");
            } else {
                printf("Error, " . $top_mx . " HELO fails!" . PHP_EOL);
                return $bRetcode;
            }
        }
        $fromAddress = "jeff@hendricom.com";
        $emailAddress = trim($emailAddress);
        $out = "MAIL FROM:<" . $fromAddress . ">\r\n";
        printf("Sending from " . $out);
        fwrite($fp, $out);
        $response = fgets($fp, 1024);
        if (substr($response, 0, 3) != "250") {
            printf("Error, setting from fails for " . $fromAddress);
            return $bRetcode;
        }
        // this is really where the rubber hits the road...
        // you're connected to the SMTP server and asking it to delivery a message to the MBOX
        // if you get a 250 on this, this email is valid
        $out = "RCPT TO:<" . $emailAddress . ">\r\n";
        printf("Sending to " . $out);
        fwrite($fp, $out);
        $response = fgets($fp, 1024);
        if (substr($response, 0, 3) != "250") {
            printf("Error, mailbox " . $emailAddress . " fails!" . PHP_EOL);
            return $bRetcode;
        }
    }

    fclose($fp);
    // no errors email is valid
    $bRetcode = true;
    printf($emailAddress . " email address validated!" . PHP_EOL);
    return $bRetcode;
}
?>        