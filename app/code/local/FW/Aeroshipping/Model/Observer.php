<?php

class FW_Aeroshipping_Model_Observer {

    public function getAeroShippingStatusExport(Mage_Cron_Model_Schedule $schedule=null)
    {
        $helper = Mage::helper('fw_aeroshipping');

        $active = $helper->isActive();

        //Only grab the XML file if the module is active
        if($active == true) {
            # ftp-login
            $ftp_server = $helper->getFtpHost();
            $ftp_user = $helper->getFtpUser();
            $ftp_pw = $helper->getFtpPassword();
            $ftp_folder = $helper->getFtpFolder();

            $errorFile = 'aeroshipping_error.log';

            // set up basic connection
            $conn_id = ftp_connect($ftp_server);
            $errorCount = 0;

            // login with username and password
            if ($conn_id == false) {
                echo "Connection to ftp server failed\n";
                Mage::log('Connection to ftp server failed\r\n', null, $errorFile);
                $this->sendEmail('Aero Order Shipments Update - Connection to ftp server failed', 'Connection to ftp server failed', Mage::getBaseDir() . '/var/log/', null);
                throw new Exception('Connection to ftp server failed.');
                return;
            }

            $login_result = ftp_login($conn_id, $ftp_user, $ftp_pw);

            if ($login_result == false) {
                echo "Login to ftp server failed\n";
                Mage::log('Login to ftp server failed\r\n', null, $errorFile);
                $this->sendEmail('Aero Order Shipments Update - Login to ftp server failed', 'Login to ftp server failed', Mage::getBaseDir() . '/var/log/', null);
                throw new Exception('Login to ftp server failed.');
                return;
            }

            // turn passive mode on
            ftp_pasv($conn_id, true);

            ftp_chdir($conn_id, $ftp_folder);

            // get current directory
            $dir = ftp_pwd($conn_id);

            $rawfiles = ftp_rawlist($conn_id, '-1t');
            $filesSorted = array();

            foreach ($rawfiles as $file) {
                $filesSorted[ftp_mdtm($conn_id, $file)] = $file;
            }

            ksort($filesSorted);

            foreach ($filesSorted as $modDate => $fileName) {
                $local_file = Mage::getBaseDir() . '/var/importexport/aeroshipping/' . $fileName;

                $deleteFile = ftp_get($conn_id, $local_file, $fileName, FTP_BINARY);

                //If ftp_get return true, delete the file from the server to prevent downloading and processing again
                if ($deleteFile) {
                    //create queue item to process aero xml file
                    $this->createAeroQueueItem($local_file);
                    ftp_delete($conn_id, $fileName);
                } else {
                    $message = "Download of {$fileName} failed";
                    Mage::log('Download of ' . $fileName . ' failed\r\n', null, $errorFile);
                    $this->sendEmail($message, $message, null, null);
                }

            }

            // close the connection
            ftp_close($conn_id);


            return $errorCount;
        }
    }

    public function createAeroQueueItem($file)
    {
        //INIT A NEW FW_Queue_Model_Queue OBJECT
        $queue = Mage::getModel('fw_queue/queue');

        //BUILD DATA ARRAY TO STORE IN QUEUE
        $queueItemData = array(
            'type' => 'aero_create_order_shipments',
            'file' => $file
        );

        //SEND QUEUE DATA ARRAY AND SUBMIT A NEW QUEUE RECORD
        $queue->addToQueue('aeroshipping/process', 'process', $queueItemData, 'process shipping status', "Process Order Shipping Status for " . basename($file));

    }

    /**
     * Send email success and/or failure
     * @param $subject string
     * @param $bodyMsg string
     * @param $filePath string
     * @param $file string
     */
    private function sendEmail($subject, $bodyMsg, $filePath, $file)
    {
        $helper = Mage::helper('fw_aeroshipping');

        $email_from = "Magento/Aeroshipping Shipment Processer";
        $fileatt = $filePath.$file; // full Path to the file
        $fileatt_type = "application/text"; // File Type

        $to =  $helper->getEmailNotice();
        $subject = $subject;
        $fileatt_name = $file;
        $file = fopen($fileatt,'rb');
        $data = fread($file,filesize($fileatt));
        fclose($file);
        $semi_rand = md5(time());
        $mime_boundary = "==Multipart_Boundary_x{$semi_rand}x";
        $headers = "From:".$email_from;
        $headers .= "\nMIME-Version: 1.0\n" .
            "Content-Type: multipart/mixed;\n" .
            " boundary=\"{$mime_boundary}\"";
        $email_message = $bodyMsg;
        $email_message .= "This is a multi-part message in MIME format.\n\n" .
            "--{$mime_boundary}\n" .
            "Content-Type:text/html; charset=\"iso-8859-1\"\n" .
            "Content-Transfer-Encoding: 7bit\n\n" .
            $email_message .= "\n\n";
        $data = chunk_split(base64_encode($data));
        $email_message .= "--{$mime_boundary}\n" .
            "Content-Type: {$fileatt_type};\n" .
            " name=\"{$fileatt_name}\"\n" .
            "Content-Transfer-Encoding: base64\n\n" .
            $data .= "\n\n" .
                "--{$mime_boundary}--\n";

        //Send email
        $ok = @mail($to, $subject, $email_message, $headers);
    }

}