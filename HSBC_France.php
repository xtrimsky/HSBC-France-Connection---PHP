<?php

/*
 * MIT License
 * Author: Andrei Pervychine
 */

define('HSBC_LINK1', 'https://client.hsbc.fr/cgi-bin/emcgi?Appl=WEBACC');
define('HSBC_LINK2', 'https://client.hsbc.fr');

class Bank_hsbc_fr {
    
    private $account_number;
    private $password;
    
    function setAccountNumber($account_number){
        $this->account_number = $account_number;
    }
    
    function setPassword($password){
        $this->password = $password;
    }

    /*
     * connects to HSBC, and returns results
     */
    function connect() {
        if(empty($this->account_number) || empty($this->password)){
            return $this->analyseFinalData('The information you have entered does not match our records.');
        }
        
        //################################################
        //#### first connection, sends account number ####
        $header = array();
        $header[] = 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';
        $header[] = 'Accept-Language: en-us,en;q=0.5';
        $header[] = 'Accept-Encoding: gzip,deflate';
        $header[] = 'Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7';
        $header[] = 'Connection: Keep-Alive';
        $header[] = 'Expect:';
        $header[] = 'User-Agent: Mozilla/5.0 (Windows; U; Windows NT 6.1; en-US; rv:1.9.2) Gecko/20100115 Firefox/3.6';
        $header[] = 'Keep-Alive: 115';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, HSBC_LINK1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);

        $post_data = $this->createPostData1($this->account_number);

        $fields_string = '';
        foreach ($post_data as $key => $value) {
            $fields_string .= $key . '=' . $value . '&';
        }
        $fields_string = rtrim($fields_string, '&');
        curl_setopt($ch, CURLOPT_POST, count($post_data));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);

        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        $data1 = curl_exec($ch);
        //#### first connection ended
        //###########################

        //gets cookies from previous connection
        $cookies = $this->getCookies($data1);

        //figures out the next link
        $search = '<form name="FORM_SECRET" method="post" action=';
        $pos = strpos($data1, $search);
        $temp = substr($data1, $pos + strlen($search));
        $pos = strpos($temp, ' ');
        $link = substr($temp, 0, $pos);

        //##################################################
        //#### second connection, now sends the password####
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, HSBC_LINK2 . $link);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);

        $post_data = $this->createPostData2($this->password);

        $fields_string = '';
        foreach ($post_data as $key => $value) {
            $fields_string .= $key . '=' . $value . '&';
        }
        $fields_string = rtrim($fields_string, '&');
        curl_setopt($ch, CURLOPT_POST, count($post_data));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);
        curl_setopt($ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($ch, CURLOPT_COOKIE, $cookies);
        $data2 = curl_exec($ch);
        //#### ends sencond connection ####
        //#################################

        //figures out final link
        $search = 'url = "';
        $pos = strpos($data2, $search);
        $temp = substr($data2, $pos + strlen($search));
        $pos = strpos($temp, '&');
        $link = substr($temp, 0, $pos);

        //#############################################
        //#### final connection, gets all the data ####
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, HSBC_LINK2 . $link);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_COOKIE, $cookies);
        $data3 = curl_exec($ch);
        //###### end final connection #################
        //#############################################
        
        curl_close($ch);

        return $this->analyseFinalData($data3);
    }

    function analyseFinalData($data) {
        $response = array();

        //not responding
        $code = 0;
        if (strpos($data, 'rer vos comptes')) {
            //we have access to the bank
            $code = 1;
        } elseif (strpos($data, 'Invalid entry. Passwords must be between 8 and 32 characters.')) {
            //bad password
            $code = 3;
        } elseif (strpos($data, 'The information you have entered does not match our records.')) {
            //bad password
            $code = 3;
        }

        $response['code'] = $code;

        if ($code == 1) {
            $accounts = array();
            $temp = $data;
            while ($pos = strpos($temp, 'loupe.gif')) {
                $account = array();
                $temp = substr($temp, $pos + strlen('loupe.gif'));

                $pos2 = $this->strnpos($temp, '>', 3);
                $temp = substr($temp, $pos2 + 1);

                $pos2 = strpos($temp, '<');
                $account['name'] = utf8_encode(substr($temp, 0, $pos2));
                if (substr($account['name'], 0, 12) == 'COMPTE&nbsp;') {
                    $account['name'] = substr($account['name'], 12);
                }

                $pos2 = strpos($temp, 'nowrap>');
                $temp = substr($temp, $pos2 + strlen('nowrap>'));
                $pos2 = strpos($temp, '<');
                $account['balance'] = str_replace(' ', '', substr($temp, 0, $pos2));
                $account['currency'] = 'EUR';

                $accounts[] = $account;
            }

            $response['accounts'] = $accounts;
        }

        return $response;
    }

    /*
     * generates post data for sending password
     */
    function createPostData2($pass) {
        $data = array(
            'Secret' => $pass
        );
        return $data;
    }

    //gets cookies from a page
    function getCookies($data) {
        $pos = strpos($data, '<html>');
        $temp = substr($data, 0, $pos);

        $search = 'Set-Cookie: ';

        $cookies = '';
        while ($pos = strpos($temp, $search)) {
            $temp1 = substr($temp, $pos + strlen($search));
            $pos2 = strpos($temp1, ';');
            $cookie = substr($temp1, 0, $pos2);

            $cookies .= $cookie . '; ';

            $temp = substr($temp, $pos + strlen($search) + $pos2);
        }

        return $cookies;
    }

    /*
     * generates post data, for sending account number
     */
    function createPostData1($account_number) {
        $data = array(
            'Appl' => 'WEBACC',
            'CODE_ABONNE' => $account_number,
            'Ident' => $account_number,
            'cpts' => '0',
            'nextPage' => 'gp.hbfr.Linking.CAM10TO30',
            'secret' => '',
            'userid' => 'gp.hbfr.Linking.CAM10TO30'
        );

        return $data;
    }
    
    /*
     * function that finds any occurence of a string (not: first occurence like strpos)
     */
    function strnpos( $haystack, $needle, $nth, $offset = 0 )
    {
        if( 1 > $nth || 0 === strlen( $needle ) )
        {
            return false;
        }
        --$offset;
        do
        {
            $offset = strpos( $haystack, $needle, ++ $offset );
        } while( --$nth  && false !== $offset );

        return $offset;
    }

}

