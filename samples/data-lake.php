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
         * eightByEight: Collect data from 8x8 API.
         * @returns void
         */

        public function eightByEight() : void {

            try {

                $date = date('Ymd');
                $data = $this->interfaces->eightByEightData();
                $path = '/home/automate/writable/reports/';
                $attachmentPaths = [];

                foreach ($data as $index => $value) {

                    $report = [];
                    $filename = '8x8_' . $index . '_' . $date . '.xlsx';
                    $header = false;

                    foreach ($value as $value_item) {

                        if ($header === false) {
                            $headers = []; // Headers
                            foreach ($value_item as $sub_index => $sub_value) $headers[] = $sub_index;
                            $report[] = $headers;
                            $header = true;
                        }
                        $row = []; // Rows
                        foreach ($value_item as $sub_value) $row[] = $sub_value;
                        $report[] = $row;

                    }

                    $writer = new XLSXWriter();
                    $writer->writeSheet($report);
                    $writer->writeToFile($path . $filename);
                    $attachmentPaths[] = $path . $filename;

                }

                // Send Report

                $subject = '8x8 Data Extracts';
                $message = 'Please find the daily 8x8 data extracts attached to this email.';
                $emails = array();
                $this->communications->send8x8Report($emails, $subject, $message, $attachmentPaths);
                usleep(mt_rand(2000000, 2500000));
                foreach ($attachmentPaths as $filePath) {
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }
                }
                return;

                // ================================================
                // Agent Inbound / Outbound Fields
                // ================================================

                $filename = 'eight_by_eight_agent_inbound_outbound_' . $date . '.xlsx';

                // Header

                $insert = array(
                    'Date',
                    'Direction',
                    'Agent',
                    'Agent ID',
                    'Media',
                    'Channel',
                    'Queue',
                    'Queue ID',
                    'Group',
                    'Presented',
                    'Accepted',
                    'Rejected',
                    'Abandoned',
                    'Accepted %',
                    'Rejected %',
                    'Abandoned %',
                    'Handling Time',
                    'Wrap Up Time',
                    'Busy Time',
                    'Average Handling Time',
                    'Average Hold Time',
                    'Average Wrap Up Time',
                    'Average Busy Time',
                    'Transfers Initiated %',
                    'Processing Time',
                    'Alerting',
                    'Average Speed To Answer',
                    'Blind Transfer To Agent',
                    'Blind Transfer To Queue',
                    'Blind Transfers Initiated',
                    'Blind Transfer Received',
                    'Hold',
                    'Hold Time',
                    'Longest Hold Time',
                    'Longest Offering Time',
                    'Offering Time',
                    'Reject Timeout',
                    'Transfers Initiated',
                    'Transfers Received',
                    'Warm Transfers Completed',
                    'Warm Transfers Received'
                );

                $report[] = $insert;

                // Data Rows

                foreach ($data as $row) {

                    $date = date('m/d/Y H:i A', strtotime($row['create-timestamp']));
                    $answered = date('m/d/Y H:i A', strtotime($row['accept-timestamp']));

                    $pattern = '/\([^()]*\)/'; // Matches the first set of parentheses and everything between them
                    $agentNames = preg_replace($pattern, '', $row['agent-name']);
                    $agentNames = rtrim($agentNames);

                    $insert = array(

                        /*'Date' => $date,
                        'Name' => rtrim($agentNames),
                        'Answered' => $answered,
                        'Handling Time' => $row['process-time'],
                        'Wrap Up Time' => $row['post-process-time'],
                        'Total Time' => $row['total-time'],
                        'Time in IVR' => $row['ivr-treatment-time'],
                        'Hold Count' => $row['hold-count'],
                        'Total Hold Time' => $row['total-hold-time']*/

                        'Date' => $row[''],
                        'Direction' => $row[''],
                        'Agent' => $row[''],
                        'Agent ID' => $row[''],
                        'Media' => $row[''],
                        'Channel' => $row[''],
                        'Queue' => $row[''],
                        'Queue ID' => $row[''],
                        'Group' => $row[''],
                        'Presented' => $row[''],
                        'Accepted' => $row[''],
                        'Rejected' => $row[''],
                        'Abandoned' => $row[''],
                        'Accepted %' => $row[''],
                        'Rejected %' => $row[''],
                        'Abandoned %' => $row[''],
                        'Handling Time' => $row[''],
                        'Wrap Up Time' => $row[''],
                        'Busy Time' => $row[''],
                        'Average Handling Time' => $row[''],
                        'Average Hold Time' => $row[''],
                        'Average Wrap Up Time' => $row[''],
                        'Average Busy Time' => $row[''],
                        'Transfers Initiated %' => $row[''],
                        'Processing Time' => $row[''],
                        'Alerting' => $row[''],
                        'Average Speed To Answer' => $row[''],
                        'Blind Transfer To Agent' => $row[''],
                        'Blind Transfer To Queue' => $row[''],
                        'Blind Transfers Initiated' => $row[''],
                        'Blind Transfer Received' => $row[''],
                        'Hold' => $row[''],
                        'Hold Time' => $row[''],
                        'Longest Hold Time' => $row[''],
                        'Longest Offering Time' => $row[''],
                        'Offering Time' => $row[''],
                        'Reject Timeout' => $row[''],
                        'Transfers Initiated' => $row[''],
                        'Transfers Received' => $row[''],
                        'Warm Transfers Completed' => $row[''],
                        'Warm Transfers Received' => $row['']

                    );

                    $report[] = $insert;

                }

                $attachmentPath = $this->utilities->packageXls($report, $path, $filename);

            } catch (\Exception $error) {

                $log = 'Automation failure for Eight by Eight (eightByEight) (' . $error . ')';
                $this->events->log('system', null, 'Critical', NULL, $log, true, true);
                return;

            }

        }

    }
