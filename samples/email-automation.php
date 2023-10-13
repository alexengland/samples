<?php

    namespace App\Controllers;

    require_once('/home/automate/app/ThirdParty/vendor/autoload.php');

    use AllowDynamicProperties;
    use CodeIgniter\Controller;
    use App\Libraries\Amazon;
    use App\Libraries\Communications;
    use App\Libraries\Events;
    use App\Libraries\Format;
    use App\Libraries\Interfaces;
    use App\Libraries\Structures;
    use App\Libraries\Html2Text;
    use App\Libraries\Utilities;
    use App\Libraries\XLSXWriter;
    use App\Models\ReportingModel;
    use Config\Database;
    use Config\Services;
    use Webklex\PHPIMAP\ClientManager;
    use Webklex\PHPIMAP\Client;
    use Webklex\PHPIMAP\Message;

    #[AllowDynamicProperties] class Automations extends Controller {

        public function __construct() {

            $this->cli = $this->isCli();
            $this->cli = (is_bool($this->cli)) ? $this->cli : false;
            if ($this->cli === true) {
                $this->ip = 'CLI';
            } else {
                $this->ip = (!empty($_SERVER['REMOTE_ADDR'])) ? $_SERVER['REMOTE_ADDR'] : 'Empty IP';
            }

            $this->db = Database::connect();
            $this->request = Services::request();
            $this->uri = Services::uri();
            $this->format = new Format();
            $this->structures = new Structures();
            $this->html2text = new Html2Text();
            $this->events = new Events($this->db, $this->request, $this->format, $this->structures, $this->cli);
            $this->utilities = new Utilities($this->events);
            $this->communications = new Communications($this->events);
            $this->amazon = new Amazon($this->events);
            $this->interfaces = new Interfaces($this->db, $this->request, $this->events, $this->format, $this->uri);
            $this->reportingModel = new ReportingModel($this->db, $this->events, $this->format, $this->request);

        }

        public function index() {

            return $this->response->setStatusCode(403);

        }

        /*
         * emailUploads: Check Office 365 mailboxes.
         * @returns void
         */

        public function emailUploads() : void {

            try {

                // 11PM - 5AM REST

                date_default_timezone_set('Australia/Sydney');
                $current_time = date('G');
                if ($current_time >= 23 || ($current_time >= 0 && $current_time < 5)) {
                    return;
                }

                // Inputs

                $log = []; // Email Automation log.

                // Connect to mailboxes using OAuth2 - Shan.

                $TENANT = '';
                $CLIENT_ID = '';
                $CLIENT_SECRET = '';
                $SCOPE = 'https://outlook.office365.com/.default offline_access';
                $imapUrl = 'https://login.microsoftonline.com/' . $TENANT . '/oauth2/v2.0/token';

                $curlParams = [
                    'client_id' => $CLIENT_ID,
                    'client_secret' => $CLIENT_SECRET,
                    'grant_type' => 'client_credentials',
                    'scope' => $SCOPE,
                ];

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $imapUrl);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($curlParams));
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

                $oResult = curl_exec($ch);
                $oResult = json_decode($oResult, true);
                $accessToken = $oResult['access_token'];
                $username = '';

                // Setup connection object

                $cm = new ClientManager();
                $client = $cm->make([
                    'host' => 'outlook.office365.com',
                    'port' => 993,
                    'encryption' => 'ssl',
                    'validate_cert' => false,
                    'username' => $username,
                    'password' => $accessToken,
                    'protocol' => 'imap',
                    'authentication' => 'oauth'
                ]);

                // Connect to the IMAP Server

                $client->connect();
                $inbox = $client->getFolder('INBOX');
                $messages = $inbox->query()->all()->softFail()->limit(500)->get();
                if (empty($messages) || count($messages) < 1) return;

                // Get current claim prefixes

                $url = '';

                $context = stream_context_create(
                    array(
                        "http" => array(
                            "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36"
                        ),
                        'ssl' => array(
                            'verify_peer' => false,
                            'verify_peer_name' => false
                        )
                    )
                );

                $json = file_get_contents($url, false, $context);
                $claimPrefixes = json_decode($json, true);
                if (empty($claimPrefixes)) throw new \Exception('Could not get claim prefixes from CFT');

                // Loop messages

                $viewed = [];
                $processed = [];
                $likelySocial = ['linkedin', 'twitter', 'facebook', 'youtube'];

                foreach ($messages as $message) {

                    // Get message details

                    $uid = $message->getUid();
                    $to = $message->getTo()->toArray()[0]->mail;
                    $from = strtolower($message->getFrom()->toArray()[0]->mail);
                    $fromFull = $message->getFrom()->toArray()[0]->full . ' <' . $from . '>';
                    $sent = $message->getDate()->toDate()->format('d-M-Y');
                    $messageSubject = strip_tags($message->getSubject()->toString());
                    $viewed[] = $messageSubject;

                    // Rate limit

                    if (count($processed) > 5) break;

                    // Check message subject

                    $messageSubject = htmlspecialchars($messageSubject, ENT_QUOTES, 'UTF-8');
                    if (empty($messageSubject)) continue;

                    // Remove hash and | bracket line

                    $messageSubject = str_replace(['#', '|'], ' ', $messageSubject);

                    // Remove multiple spaces

                    $messageSubject = preg_replace('/\s+/', ' ', $messageSubject);
                    if (empty($messageSubject)) continue;

                    // Explode message subject

                    $subjectParts = explode(' ', $messageSubject);
                    if (empty($subjectParts)) continue;

                    // Remove punctuation from message parts

                    $subjectPartsFormatted = [];

                    foreach ($subjectParts as $subjectPart) {

                        $subjectPart = strtoupper($subjectPart);
                        $subjectPart = preg_replace('/[^A-Za-z0-9]/', '', $subjectPart);
                        $subjectPartsFormatted[] = $subjectPart;

                    }

                    $subjectPartsFormatted = array_unique($subjectPartsFormatted);

                    // Loop over subject parts looking for claim prefix matches

                    $matchedIds = [];

                    foreach ($subjectPartsFormatted as $subjectPart) {

                        $subjectPartLength = strlen($subjectPart);

                        if (
                            !empty($subjectPart)
                            && $subjectPartLength > 7
                            && $subjectPartLength < 20
                            && !is_numeric($subjectPart[0])
                        ) {

                            // Loop over $claimPrefixes and see if $subjectPart starts with prefix.

                            foreach ($claimPrefixes as $claimPrefix) {

                                $length = strlen($claimPrefix);
                                $extract = substr($subjectPart, 0, $length); // Extract first (n) on subject part.
                                $numericSequence = substr($subjectPart, $length); // Get the numeric part of the claim number.

                                if (
                                    $extract == $claimPrefix
                                    && is_numeric($numericSequence)
                                    && strlen($numericSequence) > 4
                                ) {

                                    $matchedIds[] = strtoupper($subjectPart);

                                }

                            }

                        }

                    }

                    // Search database for Claim Numbers

                    $matches = [];

                    if (!empty($matchedIds)) {

                        $params = ['claims' => $matchedIds];
                        $query = http_build_query($params);
                        $url = '' . $query;

                        $context = stream_context_create(
                            array(
                                "http" => array(
                                    "header" => "User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36"
                                ),
                                'ssl' => array(
                                    'verify_peer' => false,
                                    'verify_peer_name' => false
                                )
                            )
                        );

                        $json = file_get_contents($url, false, $context);
                        $matches = json_decode($json, true);

                    }

                    // Construct log array.

                    $subLog = [];
                    $subLog['mailbox'] = $username;
                    $subLog['status'] = 0;
                    $subLog['subject'] = $messageSubject;

                    // If $matched_id contains more than 1 match, it is inconclusive and must be left alone.
                    // Not processed because complex

                    if (count($matches) > 1) {

                        $subLog = array(

                            'note_id' => 0,
                            'mailbox' => $username,
                            'status' => 3, // 3 = complex email
                            'created' => $sent,
                            'subject' => $messageSubject,
                            'email_to' => $to,
                            'email_from' => $fromFull,
                            'claim_number' => 0,
                            'claims_officer' => 0,
                            'attachments' => 0

                        );

                        $log[$uid] = $subLog;

                        continue;

                    }

                    $insClaimNo = (!empty($matches[0]['claim_number'])) ? $matches[0]['claim_number'] : '';
                    $insClaimNo = preg_replace('/[^A-Z0-9]/', '', $insClaimNo);
                    $insClaimNoLength = strlen($insClaimNo);

                    // Not processed because no claim match

                    if (empty($insClaimNo) || $insClaimNoLength < 5 || $insClaimNoLength > 25) {

                        $subLog = array(

                            'note_id' => 0,
                            'mailbox' => $username,
                            'status' => 2, // 2 = unmatched email
                            'created' => $sent,
                            'subject' => $messageSubject,
                            'email_to' => $to,
                            'email_from' => $fromFull,
                            'claim_number' => 0,
                            'claims_officer' => 0,
                            'attachments' => 0

                        );

                        $log[$uid] = $subLog;

                        continue;

                    }

                    // Add to claim details to log

                    $subLog['claim_number'] = $matches[0]['claim_number'];
                    $subLog['claims_officer'] = $matches[0]['claims_officer'];

                    // If it matches a valid claim in the database, create a new correspondence note (ref: process_new_note in ics_all.php).

                    // *** EMAIL EXTRACTION ***

                    // Header details.

                    $messageDate = $message->getDate()->toDate()->format('l\, jS F Y g:i a');
                    $subLog['created'] = $messageDate;

                    $subject = strip_tags($message->getSubject()->toString());
                    $subLog['subject'] = $messageSubject;

                    $to = array();
                    foreach ($message->getTo()->toArray() as $toAddress) {
                        $to[] = $toAddress->full . ' <' . $toAddress->mail . '>';
                    }
                    $to = implode(', ', $to);

                    $cc = array();
                    if ($message->getCc()) {
                        foreach ($message->getCc()->toArray() as $ccAddress) {
                            $cc[] = $ccAddress->full . ' <' . $ccAddress->mail . '>';
                        }
                    }
                    $cc = implode(', ', $cc);
                    if (!empty($cc)) {
                        $cc = '
                    Cc: ' . $cc;
                    }

                    $subLog['email_to'] = $to;

                    // Compile From

                    $from = $message->getFrom()->toArray()[0]->full . ' <' . $message->getFrom()->toArray()[0]->mail . '>';
                    if (empty($from)) $from = 'Unknown';
                    $subLog['email_from'] = $from;

                    // Retain original message object for later use

                    $originalMessageObject = $message;

                    // If there is a single .eml attachment, drill into it and make it the message object.

                    $embeddedAttachments = $message->getAttachments();
                    $embeddedAttachmentsCount = count($embeddedAttachments);
                    if ($embeddedAttachmentsCount === 1) {
                        foreach ($embeddedAttachments as $embeddedAttachment) {
                            $mimeType = $embeddedAttachment->getMimeType();
                            if (strtolower($mimeType) === 'message/rfc822') {

                                // Save embedded email to temporary file

                                $milliseconds = round(microtime(true) * 1000);
                                $embeddedAttachmentTempFileName = 'email_' . $uid . '_' . mt_rand() . '_' . $milliseconds . '.eml';
                                $tempFilePath = '';
                                $tempFullPath = $tempFilePath . $embeddedAttachmentTempFileName;
                                if (!$embeddedAttachment->save($tempFilePath, $embeddedAttachmentTempFileName)) throw new \Exception('Unable to save unwrapped .eml');

                                // Parse the .eml content and replace existing email message object

                                $message = Message::fromFile($tempFullPath);

                            }
                        }
                    }

                    // Get email message body.

                    if (!empty($message->getHTMLBody())) {
                        $messageBody = $message->getHTMLBody();
                    } else {
                        $messageBody = strip_tags($message->getTextBody());
                    }

                    $messageBody = $this->html2text->convert($messageBody);

                    // Remove url defense links

                    $messageBody = preg_replace('/\(https:\/\/urldefense.*?\)/', '', $messageBody);

                    // Strip non-printable characters

                    $messageBody = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\xFF]/', '', $messageBody); // remove non-printable ASCII characters except line breaks
                    $messageBody = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\xFF]+/u', ' ', $messageBody); // replace consecutive non-printable ASCII characters with a single space

                    if (empty($messageBody)) {

                        $messageBody = 'The message body of this email could not be displayed. Please refer to the original email.';

                    }

                    // Compose the final note body.

                    $messageBody = 'From: ' . $fromFull . '
            Sent: ' . $messageDate . '
            To: ' . $to . $cc . '
            Subject: ' . $subject . '
          
            ' . $messageBody . '
            
            ';

                    // Insert new note on CFT

                    $url = '';

                    $postData = http_build_query(
                        array(
                            'claim' => $insClaimNo,
                            'message' => $messageBody
                        )
                    );

                    $context = stream_context_create(
                        array(
                            "http" => array(
                                "method" => "POST",
                                "header" => array(
                                    "Content-Type: application/x-www-form-urlencoded",
                                    "User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36"
                                ),
                                "content" => $postData

                            ),
                            'ssl' => array(
                                'verify_peer' => false,
                                'verify_peer_name' => false
                            )
                        )
                    );

                    $json = file_get_contents($url, false, $context);
                    $noteId = json_decode($json, true);
                    if (empty($noteId)) continue;

                    // Add to log

                    $subLog['note_id'] = $noteId;

                    // Get attachments and save.

                    $safeFiles = array('png', 'jpeg', 'jpg', 'png', 'bmp', 'gif', 'tif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'mp3', 'wav', 'wma', 'mp3', 'bin');

                    $files = $message->getAttachments();
                    $fileCount = 0;

                    // Save original email as first attachment

                    try {

                        // Name the email file

                        $originalFilename = 'original_email.eml';
                        $milliseconds = round(microtime(true) * 1000);
                        $prefix = 'email_' . $noteId . '_' . mt_rand() . '_' . $milliseconds . '_';
                        $uniqueFilename = $prefix . $originalFilename;

                        // Save to local disk

                        $fullPath = '' . $uniqueFilename;
                        $originalMessageObject->save($fullPath);

                        // Upload to Amazon S3

                        $result = $this->amazon->amazonCftEmailFileUpload($uniqueFilename, $fullPath);

                        if (!empty($result)) {

                            $fileSize = filesize($fullPath);

                            $url = '';

                            $postData = http_build_query(
                                array(
                                    'noteid' => $noteId,
                                    'status' => 0,
                                    'displayname' => 'Original_Email.eml',
                                    'filename' => $uniqueFilename,
                                    'filesize' => $fileSize
                                )
                            );

                            $context = stream_context_create(
                                array(
                                    'http' => array(
                                        'method' => 'POST',
                                        'header' => array(
                                            'Content-Type: application/x-www-form-urlencoded',
                                            'User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36'
                                        ),
                                        'content' => $postData

                                    ),
                                    'ssl' => array(
                                        'verify_peer' => false,
                                        'verify_peer_name' => false
                                    )
                                )
                            );

                            $json = file_get_contents($url, false, $context);
                            $attachmentId = json_decode($json, true);

                            if (!empty($attachmentId)) {
                                $fileCount = $fileCount + 1;
                            }

                        }

                    } catch (\Exception $error) {

                        mail('', 'Save Original Email Error', print_r($error, true));

                    }

                    // Save inline img base64 images as attachments

                    $embeddedImages = $this->extractEmbeddedBase64Images($message);

                    if (!empty($embeddedImages)) {

                        foreach ($embeddedImages as $embeddedImage) {

                            $fileName = $embeddedImage['filename'];

                            // Social media icon check

                            $isSocial = false;
                            foreach ($likelySocial as $smIcon) {
                                if (strpos(strtolower($fileName), $smIcon) !== false) {
                                    $isSocial = true;
                                    break;
                                }
                            }
                            if ($isSocial === true) continue;

                            // Save to local disk

                            $fullPath = '' . $fileName;
                            file_put_contents($fullPath, $embeddedImage['content']);

                            // Upload to Amazon S3

                            $result = $this->amazon->amazonCftEmailFileUpload($fileName, $fullPath);
                            if (empty($result)) continue;

                            $fileSize = filesize($fullPath);

                            $url = '';

                            $postData = http_build_query(
                                array(
                                    'noteid' => $noteId,
                                    'status' => 0,
                                    'displayname' => $fileName,
                                    'filename' => $fileName,
                                    'filesize' => $fileSize
                                )
                            );

                            $context = stream_context_create(
                                array(
                                    'http' => array(
                                        'method' => 'POST',
                                        'header' => array(
                                            'Content-Type: application/x-www-form-urlencoded',
                                            'User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64)AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36'
                                        ),
                                        'content' => $postData

                                    ),
                                    'ssl' => array(
                                        'verify_peer' => false,
                                        'verify_peer_name' => false
                                    )
                                )
                            );

                            $json = file_get_contents($url, false, $context);
                            $attachmentId = json_decode($json, true);
                            if (empty($attachmentId)) continue;
                            $fileCount = $fileCount + 1;

                        }

                    }

                    // Save normal attachments

                    if (!empty($files)) {

                        foreach ($files as $file) {

                            // Save attachment references to database

                            $status = 0;
                            $fileName = (!empty($file->getName())) ? $file->getName() : '';
                            $fileName = mb_ereg_replace("([^\w\s\d\-_~,;\[\]\(\).])", '', $fileName);
                            $fileName = mb_ereg_replace("([\.]{2,})", '', $fileName);
                            $fileName = preg_replace('/\s+/', '_', $fileName);
                            $fileName = (preg_match('/^([-\.\w]+)$/', $fileName)) ? $fileName : '';

                            // Social media icon check

                            $isSocial = false;
                            foreach ($likelySocial as $smIcon) {
                                if (strpos(strtolower($fileName), $smIcon) !== false) {
                                    $isSocial = true;
                                    break;
                                }
                            }
                            if ($isSocial === true) continue;

                            // Create file

                            $milliseconds = round(microtime(true) * 1000);
                            $prefix = 'file_' . $noteId . '_' . mt_rand() . '_' . $milliseconds . '_';
                            $uniqueFilename = strtolower($prefix . $fileName);
                            $filePath = '/home/automate/writable/attachments/cft_email_uploads/';
                            $fullPath = $filePath . $uniqueFilename;
                            if (!$file->save($filePath, $uniqueFilename)) continue;

                            // Upload to Amazon S3

                            $result = $this->amazon->amazonCftEmailFileUpload($uniqueFilename, $fullPath);
                            if (empty($result)) continue;

                            $fileSize = filesize($fullPath);

                            $url = '';

                            $postData = http_build_query(
                                array(
                                    'noteid' => $noteId,
                                    'status' => $status,
                                    'displayname' => $fileName,
                                    'filename' => $uniqueFilename,
                                    'filesize' => $fileSize
                                )
                            );

                            $context = stream_context_create(
                                array(
                                    'http' => array(
                                        'method' => 'POST',
                                        'header' => array(
                                            'Content-Type: application/x-www-form-urlencoded',
                                            'User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64)AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.102 Safari/537.36'
                                        ),
                                        'content' => $postData

                                    ),
                                    'ssl' => array(
                                        'verify_peer' => false,
                                        'verify_peer_name' => false
                                    )
                                )
                            );

                            $json = file_get_contents($url, false, $context);
                            $attachmentId = json_decode($json, true);
                            if (empty($attachmentId)) continue;
                            $fileCount = $fileCount + 1;

                        }

                    }

                    // Move too 'CFT Automations' folder in motor mailbox and mark as SEEN

                    $originalMessageObject->setFlag('SEEN');
                    $originalMessageObject->move('INBOX/10. CFT Automation');

                    // Change log to show this email is now considered processed by automation

                    $subLog['status'] = 1;
                    $subLog['attachments'] = $fileCount;
                    $log[$uid] = $subLog;

                    $processed[] = $messageSubject;

                    sleep(1);

                }

                // Close connection to conserve resources

                $client->disconnect();

                // Write the Email Automation reporting log

                $this->reportingModel->logEmailAutomation($log);

            } catch (\Exception $error) {

                if (!empty($client)) $client->disconnect();

                if (
                    !empty($messageDate)
                    && !empty($messageubject)
                    && !empty($uid)
                ) {

                    $to = (!empty($message->getTo()->toArray()[0]->mail)) ? $message->getTo()->toArray()[0]->mail : '';
                    $fromAddress = $message->getFrom();
                    $fromName = (!empty($fromAddress) && !empty($fromAddress->personal)) ? $fromAddress->personal . ' ' : '';
                    $fromAddress = $fromAddress->toArray()[0]->full;
                    $fromFullDetails = (!empty($fromAddress) && !empty($fromName)) ? $fromName . ' ' . $fromAddress : '';

                    $failLog[$uid] = array(

                        'note_id' => '0',
                        'mailbox' => $username,
                        'status' => '4', // Complex Fail,
                        'subject' => $messageubject,
                        'email_to' => $to,
                        'email_from' => $fromFullDetails,
                        'claim_number' => '0',
                        'claims_officer' => '0',
                        'attachments' => '0'

                    );

                    $this->reportingModel->logEmailAutomation($failLog);

                }

                $messageSubject = (!empty($messageSubject)) ? $messageSubject : '';
                $log = 'Automation failure for Email Uploads update (emailUploads) (' . $messageSubject . ' ' . $error . ')';
                $this->events->log('system', null, 'Critical', NULL, $log, true, true);
                $this->response->setStatusCode(403);
                return;

            }

        }

        /*
         * emailUploadsReport: Generate report for email uploads.
         * @returns void
         */

        public function emailUploadsReport(): void {

            try {

                // Build Report

                $date = date('Ymd');
                $mailbox = '';
                $start = strtotime('today');
                $end = $start + 86399; // + 23 Hours 59 Minutes 59 Seconds
                $path = '';
                $filename = 'email_automation_' . $date . '.xlsx';

                $data = $this->reportingModel->getEmailAutomationLogs($mailbox, $start, $end);
                $report = [];

                if (empty($data)) $data = [];

                // Header

                $insert = array(
                    'Date',
                    'Message',
                    'Status',
                    'From',
                    'To',
                    'Claim Number',
                    'Claims Officer',
                    'Attachments'
                );

                $report[] = $insert;

                // Data Rows

                foreach ($data as $row) {

                    if ($row['status'] == 1) {
                        $status = 'Processed';
                        $attachments = $row['attachments'];
                    } else if ($row['status'] == 2) {
                        $status = 'Manual';
                        $attachments = 'N/A';
                    } else if ($row['status'] == 3) {
                        $status = 'Complex';
                        $attachments = 'N/A';
                    } else if ($row['status'] == 4) {
                        $status = 'Complex Format';
                        $attachments = 'N/A';
                    } else {
                        $status = 'Unknown';
                        $attachments = 'N/A';
                    }

                    $timestamp = date('m/d/Y H:i A', (int)$row['created']);

                    $insert = array(

                        'Date' => $timestamp,
                        'Message' => $row['subject'],
                        'Status' => $status,
                        'From' => $row['email_from'],
                        'To' => $row['email_to'],
                        'Claim Number' => $row['claim_number'],
                        'Claims Officer' => $row['claims_officer'],
                        'Attachments' => $attachments
                    );

                    $report[] = $insert;

                }

                $attachmentPath = $this->utilities->packageXls($report, $path, $filename);
                if (empty($attachmentPath)) throw new \Exception('Failed to generate XLS file (' . $path . $filename . ')');

                // Send Report

                $subject = 'Email Automation Report';
                $message = 'Please find the daily Email Automation Report attached to this email.';
                $emails = array();
                $this->communications->sendEmailUploadsReport($emails, $subject, $message, $attachmentPath);

            } catch (\Exception $error) {

                $log = 'Automation failure for Email Uploads Report update (emailUploadsReport) (' . $error . ')';
                $this->events->log('system', null, 'Critical', NULL, $log, true, true);
                return;

            }

        }

        /*
         * emailUploadsPurge: Purge emails from temp directories.
         * @returns void
         */

        public function emailUploadsPurge(): void {

            try {

                array_map('unlink', glob("/home/writable/attachments/cft_email_uploads/*"));
                array_map('unlink', glob("/home/writable/attachments/cft_embedded_images/*"));
                array_map('unlink', glob("/home/writable/attachments/cft_original_emails/*"));
                array_map('unlink', glob("/home/writable/attachments/cft_temporary_embedded_emails/*"));

                $start = strtotime('today');
                $before = $start - 259200; // - 3 days
                $this->reportingModel->pruneEmailAutomationLogs('', $before);

            } catch (\Exception $error) {

                $log = 'Automation failure for Email Uploads purge (emailUploadsReport) (' . $error . ')';
                $this->events->log('system', null, 'Critical', NULL, $log, true, true);
                return;

            }

        }

        /*
         * extractEmbeddedBase64Images: Get images from message object.
         * @param object $message // Message object
         * @returns array
         */

        private function extractEmbeddedBase64Images(object $message) : array {

            $custom_attachments = [];

            // Find all base64 encoded images in the message body

            $pattern_base64 = '/<img[^>]*src=["\']data:image\/(jpeg|png|gif);base64,([^\'"]+)["\']/i';
            preg_match_all($pattern_base64, quoted_printable_decode($message->getHTMLBody()), $matches_base64);

            // Loop over any matches and create a new attachment array

            if (isset($matches_base64[2]) && count($matches_base64[2]) > 0) {

                $counter = 1;
                $tagGroup = mt_rand();
                $milliseconds = round(microtime(true) * 1000);

                foreach ($matches_base64[2] as $index => $base64_encoded_image) {

                    // Detect the correct mime

                    $mime = 'image/' . $matches_base64[1][$index];
                    $extension = match ($mime) {
                        'image/jpeg' => '.jpg',
                        'image/png' => '.png',
                        'image/gif' => '.gif',
                        default => '.unknown',
                    };

                    if ($extension == '.unknown') continue;

                    // Create the array

                    $attachment = [
                        'id' => 'embedded_image_' . $counter . '_' . $tagGroup . '_' . $milliseconds,
                        'filename' => 'embedded_image_' . $counter . '_' . $tagGroup . '_' . $milliseconds . $extension,
                        'content' => base64_decode($base64_encoded_image),
                        'size' => strlen(base64_decode($base64_encoded_image)),
                        'mime' => $mime,
                    ];

                    $custom_attachments[] = $attachment;
                    $counter++;

                }
            }

            return $custom_attachments;

        }

        /*
         * isCli: Detect if this is a CLI connection.
         * @returns array
         */

        private function isCli(): bool {

            if (in_array(PHP_SAPI, ['cli', 'phpdbg'], true)) {
                return true;
            }

            return !isset($_SERVER['REMOTE_ADDR']) && !isset($_SERVER['REQUEST_METHOD']);

        }

}