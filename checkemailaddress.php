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
   
   // which email address would you like to validate
   $data["email"][] = "jeff@hendricom.com";
   // change this to your domain e.g. mine is hendricom.com
   $data["hellodomain"][] = "hendricom.com";
   
   $result = CheckEmailAddress($data);
   printf("Result = %s, %s\n", $result["success"], $result["message"]);
 */ 

function CheckEmailAddress($data) {
    $result = [
        "success" => true,
        "message" => "Success!",
        "email" => []
    ];

    for ($i = 0; $i < sizeof($data["email"]); $i++) {
        $emailaddress = $data["email"][$i];        
        // JKH note: this is also in the other func.php
        $domain = substr($emailaddress, strpos($emailaddress, '@') + 1);
        // get the Name Server
        exec("dig " . $domain . " ns +short", $dns);
        if($dns == "") {
            $result["success"] = false;
            $result["message"] = "One or more email addresses you entered may be invalid.<br/>NS invalid</br>Double-check to make sure they are correct before proceeding.";
            $result["email"][] = $emailaddress;
            return $result;
        }  
        $nameserver = "";
        foreach($dns as $key => $value) {
            $nameserver = $value;
        }      
        // get the MXs for the domain using the domain's Name Server
        exec("dig @" . $nameserver . " +short MX " . $domain, $mx);
        if (sizeof($mx) < 1) {
            $result["success"] = false;
            $result["message"] = "One or more email addresses you entered may be invalid.<br/>MX invalid<br/>Double-check to make sure they are correct before proceeding.";
            $result["email"][] = $emailaddress;              
            return $result;        
        }
        $top_choice = 10; // default to 10, get top choice
        $top_mx = ""; // empty mx
        foreach($mx as $key => $value) {          
            if (preg_match('/(\d+) (.*)\./', $value, $output_array)) {
                if ($top_choice >= $output_array[1]) {
                    $top_mx = $output_array[2];
                    $top_choice = $output_array[1];
                }                    
            }
        } 

        // now, you have domain name, the nameserver, and the top mx for this domain.
        // printf("Domain %s\n", $domain);
        // printf("Name server %s\n", $nameserver); 
        // printf("Top MX = %s\n", $top_mx);       

        // connect to MX for domain
        $fp = @fsockopen($top_mx, 25, $errno, $errstr, 20);
        if (!$fp) {
            $result["success"] = false;
            $result["message"] = "One or more email addresses you entered may be invalid.<br/>MX connect<br/>Double-check to make sure they are correct before proceeding.";
            $result["email"][] = $emailaddress;              
            return $result;               
        } else {
            $response = fgets($fp, 1024);
            // printf("Response %s\n", $response);             
            if (substr($response, 0, 3) != "220") {
                $error_number = substr($response, 0, 3);
                if ($error_number == 554) {
                    // how to report warning?
                    $myDebug = __FILE__ . " " . __LINE__;
                    $result["success"] = true;
                    $result["message"] = $myDebug . " Warning, connected to MX, but connection forcibly closed!";
                    // this broken connect may be because of DNS RBL reports by IP, e.g.
                    // Response 554 5.7.1 ACL dns_rbl; Client host [45.58.42.7] blocked using Spamhaus PBL 
                    // if you've gotten this far, the domain is right, the mx is right, and I would give 
                    // the email address a pass, a truly unroutable email would have failed by now...
                    // you can turn this into a fail here if you like...
                    // return positive result
                    return $result;                   
                }            
                $result["success"] = false;
                $result["message"] = "One or more email addresses you entered may be invalid.<br/>No 220 on Connect<br/>Double-check to make sure they are correct before proceeding.";
                $result["email"][] = $emailaddress;              
                return $result;             
            }         
            $out = "HELO " . $data["hellodomain"][$i] . "\r\n";
            fwrite($fp, $out); 
            $response = fgets($fp, 1024);
            if (substr($response, 0, 3) != "250") {
                $result["success"] = false;
                $result["message"] = "One or more email addresses you entered may be invalid.<br/>No 250 on HELO<br/>Double-check to make sure they are correct before proceeding.";
                $result["email"][] = $emailaddress;              
                return $result;                
            }                             
            $out = "MAIL FROM:<" . $emailaddress . ">\r\n";
            fwrite($fp, $out);          
            $response = fgets($fp, 1024);
            if (substr($response, 0, 3) != "250") {
                $result["success"] = false;
                $result["message"] = "One or more email addresses you entered may be invalid.<br/>No 250 on FROM<br/>Double-check to make sure they are correct before proceeding.";
                $result["email"][] = $emailaddress;              
                return $result;              
            }                               
        }
    }
    fclose($fp);        
    // no errors email is valid
    return $result;
} 
?>        