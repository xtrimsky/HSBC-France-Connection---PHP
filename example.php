<?php
    require_once('HSBC_France.php');
    
    $hsbc_france = new Bank_hsbc_fr();
    $hsbc_france->setAccountNumber('11111111111111');
    $hsbc_france->setPassword('111111');
    
    $result = $hsbc_france->connect();
    if($result['code'] !== 1){
        /*
         * code: 0 unknown, no connection
         * code: 2 bad password (wrong characters)
         * code: 3 bad password
         */
        throw new Exception('Could not connect to the bank');
    }else{
        echo '<pre>';
        print_r($result);
        
        /*
         * returns an array like this:
         * 
         * Array
            (
                [code] => 1
                [accounts] => Array
                    (
                        [0] => Array
                            (
                                [name] => M. ANDREI PERVYCHINE
                                [balance] => 131,54 
                                [currency] => EUR
                            )

                        [1] => Array
                            (
                                [name] => LIVRAIT A
                                [balance] => 15,54
                                [currency] => EUR
                            )

                        [2] => Array
                            (
                                [name] => LIVRET HSBC EPARGNE
                                [balance] => 820,00 
                                [currency] => EUR
                            )

                    )

            )
         */
    }
?>