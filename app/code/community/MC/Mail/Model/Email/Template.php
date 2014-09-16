<?php

class MC_Mail_Model_Email_Template extends Mage_Core_Model_Email_Template
{

    public function send($email, $name = null, array $variables = array())
    {
        if (!$this->isValidForSend()) {
            Mage::logException(new Exception('This letter cannot be sent.')); // translation is intentionally omitted
            return false;
        }


        $config = array(
                'ssl'      => Mage::getStoreConfig('system/smtp/ssl'),      // option of none, ssl or tls
                'port'     => Mage::getStoreConfig('system/smtp/port')
        );

        /* Set up mail transport to Email Hosting Provider SMTP Server via SSL/TLS */
        if(Mage::getStoreConfig('system/smtp/user')){
            $config['auth'] = 'login';
            $config['username'] = Mage::getStoreConfig('system/smtp/user');

            if(Mage::getStoreConfig('system/smtp/pass')){
                $config['password'] = Mage::getStoreConfig('system/smtp/pass');
            }
        }

        /* Set up transport package to host */
        $transport = new Zend_Mail_Transport_Smtp(Mage::getStoreConfig('system/smtp/host'), $config);
        /* End transport setup */

        $emails = array_values((array)$email);
        $names = is_array($name) ? $name : (array)$name;
        $names = array_values($names);
        foreach ($emails as $key => $email) {
            if (!isset($names[$key])) {
                $names[$key] = substr($email, 0, strpos($email, '@'));
            }
        }

        $variables['email'] = reset($emails);
        $variables['name'] = reset($names);

        // ini_set('SMTP', Mage::getStoreConfig('system/smtp/host'));
        // ini_set('smtp_port', Mage::getStoreConfig('system/smtp/port'));

        $mail = $this->getMail();

        $setReturnPath = Mage::getStoreConfig(self::XML_PATH_SENDING_SET_RETURN_PATH);


    switch ($setReturnPath) {
            case 1:
                $returnPathEmail = $this->getSenderEmail();
                break;
            case 2:
                $returnPathEmail = Mage::getStoreConfig(self::XML_PATH_SENDING_RETURN_PATH_EMAIL);
                break;
            default:
                $returnPathEmail = null;
                break;
        }




        if ($returnPathEmail !== null) {
            $mailTransport = new Zend_Mail_Transport_Sendmail("-f".$returnPathEmail);
            Zend_Mail::setDefaultTransport($mailTransport);
        }

        foreach ($emails as $key => $email) {
            $mail->addTo($email, '=?utf-8?B?' . base64_encode($names[$key]) . '?=');
        }

        $this->setUseAbsoluteLinks(true);
        $text = $this->getProcessedTemplate($variables, true);
        if($this->isPlain()) {
            $mail->setBodyText($text);
        } else {

            $mail->setBodyHTML($text);
        }
        //echo $text;die;
        $mail->setSubject('=?utf-8?B?' . base64_encode($this->getProcessedTemplateSubject($variables)) . '?=');
        $mail->setFrom($this->getSenderEmail(), $this->getSenderName());

        try {
            /* Send Transport, empty and log success */
            $mail->send($transport); //transport object
            $this->_mail = null;
            Mage::log('Mailed to: ' . $this->getSenderEmail() . ' ' . $this->getSenderName() . ' ' .$this->getProcessedTemplateSubject($variables), null, 'email.log');

        }
        catch (Exception $e) {
            /* Or empty and log failure */
            $this->_mail = null;
            Mage::log('Failure: ' . $e, null, 'email.log');
            Mage::logException($e);
            return false;
            /* End */
        }
        return true;
    }

    public function isValidForSend()
    {
        return !Mage::getStoreConfigFlag('system/smtp/disable');
    }
}
