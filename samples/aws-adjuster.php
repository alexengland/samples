<?php

    /*
     * amazonIngress: Works with AWS SDK to open ports.
     * @param string $groupId // AWS Group ID
     * @param array $ips // Array of IPs to work on
     * @param array $ports // Array of ports to work on
     * @returns void
     */

    public function amazonIngress(String $groupId = '', array $ips = array(), array $ports = array()) : void {

        /*

            The 1st IP array is a list of IPs to act upon, the 2nd array defines the
            ports for each ip in the 1st array. If an IP appears in the 1st list but
            no definition exists in the 2nd array, it will be deleted from the
            security group. IP addresses that are in the security group in the
            AWS console, but are not described in either array will be ignored and
            will remain unchanged.

        */

        try {

            if (empty($groupId)) throw new \Exception('AWS Security Group not specified.');

            $remove = array();
            $create = array();

            $ec2Client = new Ec2Client([
                'region' => 'ap-southeast-2',
                'version' => '2016-11-15'
            ]);

            $describe = $ec2Client->DescribeSecurityGroups(array('GroupId' => $groupId));

            foreach ($describe['SecurityGroups'] as $SecurityGroup) {

                if ($SecurityGroup['GroupId'] === $groupId) $SecurityGroupArray = $SecurityGroup;

            }

            if (empty($SecurityGroupArray)) throw new \Exception('AWS Security Group not found.');

            foreach ($SecurityGroupArray['IpPermissions'] as $rule) {

                if (in_array($rule['FromPort'], $ports)) {

                    $remove[] = array(

                        'IpProtocol' => 'tcp',
                        'FromPort'   => $rule['FromPort'],
                        'ToPort'     => $rule['FromPort'],
                        'IpRanges'   => array(array('CidrIp' => $rule['IpRanges'][0]['CidrIp']))

                    );

                }

            }

            if (!empty($remove)) {

                $removed = $ec2Client->RevokeSecurityGroupIngress(

                    array (

                        'GroupId' => 'sg-c9bd3aac',
                        'IpPermissions' => $remove

                    )

                );

            }

            foreach ($ports as $port) {

                $profile = explode(':', $port);

                foreach ($ips as $ip) {

                    if ($ip != $profile[1]) continue;

                    $create[] = array(

                        'IpProtocol' => 'tcp',
                        'FromPort'   => $profile[0],
                        'ToPort'     => $profile[0],
                        'IpRanges'   => array(array('CidrIp' => $ip))

                    );

                }

            }

            if (!empty($create)) {

                $created = $ec2Client->AuthorizeSecurityGroupIngress(

                    array (

                        'GroupId' => 'sg-c9b83d2ac',
                        'IpPermissions' => $create

                    )

                );

            }


        } catch (\Exception $error) {

            $log = 'Amazon Ingress Fail';
            $this->events->log('system', null, 'Critical', NULL, $log, TRUE, TRUE);
            return FALSE;

        }

    }
