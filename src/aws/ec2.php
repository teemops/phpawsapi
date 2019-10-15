<?php

namespace Teem\CloudAPI;

use \Aws\Ec2\Ec2Client;
use \Aws\Sts\StsClient;
use Aws\Exception\AwsException;
use Aws\CommandPool;
use Aws\CommandInterface;
use Aws\ResultInterface;
use GuzzleHttp\Promise\PromiseInterface;
use Exception;

/*
 * Abstracts a lot of EC2 SDK functionality.
 * Uses STS Assume Role to initiate calls to the AWS SDK/API.
 * Improvements: construct function to pass roleARN and region
 * rather than passing into every function.
 * @author Ben Fellows <kiwifellows@gmail.com>
 * 
 * 
 */
class ec2Data
{

    private $options=array();
    
    public function __construct($options=array()){
        print_r($otpions);
        $this->options=$options['iam'];
    }
    
    /*
     * Lists VPCs for given region and IAM Role
     * attached to a customer's account
     * @author Ben Fellows <kiwifellows@gmail.com>
     * 
     */
    public function listVPCs($roleArn, $region)
    {
        $ec2Client=$this->setupClient($roleArn, $region);
        $describeList=$ec2Client->describeVpcs();
        $awsJMSResult=$describeList->search('Vpcs[].{ID: VpcId, IPRange: CidrBlock}');
        return $awsJMSResult;
    }
    /*
     * Lists Subnets for given region and IAM Role
     * attached to a customer's account
     * @author Ben Fellows <kiwifellows@gmail.com>
     * 
     */
    public function listSubnets($roleArn, $region)
    {
        $ec2Client=$this->setupClient($roleArn, $region);
        $describeList=$ec2Client->describeSubnets();
        $awsJMSResult=$describeList->search('Subnets[].{ID: SubnetId, IPRange: CidrBlock, VpcId: VpcId, AvailabilityZone: AvailabilityZone, Tags: Tags[*]}');
        return $awsJMSResult;
    }
    /*
     * Lists Security Groups for given region and IAM Role
     * attached to a customer's account
     * @author Ben Fellows <kiwifellows@gmail.com>
     * 
     */
    public function listSGs($roleArn, $region)
    {
        $ec2Client=$this->setupClient($roleArn, $region);
        $describeList=$ec2Client->describeSecurityGroups();
        $awsJMSResult=$describeList->search('SecurityGroups[].{ID: GroupId, Description: Description, VpcId: VpcId, Name: GroupName, Tags: Tags[*]}');
        //return errors
        //return $options;
        return $awsJMSResult;
    }
    /*
     * Runs an AWS EC2 Task and returns output
     * attached to a customer's account
     * @author Ben Fellows <kiwifellows@gmail.com>
     * 
     */
    public function runTask($roleArn, $region, $params, $task, $jmesPath="")
    {

        //transform $params
        if($params==""){
            $params=[];
        }

        //if region has a *wildcard in it then we are going to use the multiregion task otherwise...
        if(strpos($region, "*")){
            
            return $this->runMultiRegionTask($roleArn, $region, $params, $task, $jmesPath);
        }else{

            try{
                $ec2Client=$this->setupClient($roleArn, $region);
                $command=$ec2Client->getCommand($task, $params);
                $taskOutput=$ec2Client->execute($command);

                if($jmesPath!=""){
                    $awsJMSResult=$taskOutput->search($jmesPath);
                    $result=[
                        'result'=>$awsJMSResult
                    ];

                    return $result;
                }else{
                    //only need first key which we can use to display in API response as the name of the array e.g. Reservations[]
                    foreach($taskOutput as $key => $value){
                        $result=[
                            $key=>$value
                        ];
                        return $result;
                    }
                }
            }catch(Exception $e){
                //should output correct AWS SDK Exception here
                throw new Exception($e);
            }
        }
    }
    
    /*
     * Runs a MultiRegion AWS EC2 Task and returns output
     * attached to a customer's account
     * @author Ben Fellows <kiwifellows@gmail.com>
     * 
     */
    public function runMultiRegionTask($roleArn, $region, $params, $task, $jmesPath="")
    {
        
        $ec2Client=$this->setupClient($roleArn, "us-east-1");
    
        //get all regions matching wildcard e.g. * or ap-south* or us*
        $regions=$ec2Client->getIterator(
            'DescribeRegions', 
            [
                'Filters' => [
                    [
                        'Name' => 'region-name',
                        'Values' => [$region]
                    ]
                ]
            ]
        );

        $commands=array();
        $i=0;
        //stores the request data before promise is requested
        $outputResults=array(); //used for both $outputResults[*]['region'] and $outputResults[*]['result']
        //loop through regions
        foreach($regions as $region){
            $regionclient = $this->setupClient($roleArn, $region['RegionName']); 

            array_push(
                $commands, 
                $regionclient->getCommand(
                    $task,
                    $params
                )
            );
            //need region stored in result
            $outputResults['MultiRegionResults'][$i]['region']=$region['RegionName'];

            $i++;
         
        }

        $pool = new CommandPool($ec2Client, $commands, [
            // Only send 5 commands at a time (this is set to 25 by default).
            'concurrency' => 5,
            // Invoke this function before executing each command.
            'before' => function (CommandInterface $cmd, $iterKey) {
                
            },
            // Invoke this function for each successful transfer.
            'fulfilled' => function (
                ResultInterface $taskOutput,
                $iterKey,
                PromiseInterface $aggregatePromise
            ) use($jmesPath, &$outputResults) {

                if($jmesPath!=""){
                    $awsJMSResult=$taskOutput->search($jmesPath);
                    $result=[
                        'result'=>$awsJMSResult
                    ];
                    $result=$awsJMSResult;
                    //print_r($result);
                    
                }else{
                    $i=0;
                    foreach($taskOutput as $key => $value){
                        //we only want first key which has data
                        
                        if($i==0){
                            $result=[
                                $key=>$value
                            ];
                        }
                        $i++;
                    }
                }
                return $outputResults['MultiRegionResults'][$iterKey]['results']=$result;
                //print_r($result);
                //$outputResults['MultiRegionResults'][$iterKey]['result']=$result;
            },
            // Invoke this function for each failed transfer.
            'rejected' => function (
                $reason,
                $iterKey,
                PromiseInterface $aggregatePromise
            ) {
                global $outputResults;
               
                $result=['error'=>$reason];
                array_push($outputResults, $result);
            },
        ]);

        // Initiate the pool transfers
        $promise = $pool->promise();

        // Force the pool to complete synchronously
        echo($promise->wait());
        //var_dump($outputResults);
        return $outputResults;

    }

    private function setupClient($roleArn, $region){
        //try{
            $this->client = new StsClient([
            'version' => 'latest',
            'region'  => $region
            ]);
            
            $stsclient = $this->client->assumeRole(array(
                // RoleArn is required
                'RoleArn' => $roleArn,
                // RoleSessionName is required
                //'RoleSessionName' => 'cloudapi',
                //'ExternalId' => 'cloudapi-1234',
                'RoleSessionName' => $this->options['RoleSessionName'],
                'ExternalId' => $this->options['ExternalId'],
            ));
            
            $credentials  = $stsclient->get('Credentials');
            //$credentials = $sts->createCredentials($sts->getSessionToken());
            
            $ec2Client= new Ec2Client([
                'version' => 'latest',
                'region'  => $region,
                'credentials' => [
                    'key'    => $credentials['AccessKeyId'],
                    'secret' => $credentials['SecretAccessKey'],
                    'token'  => $credentials['SessionToken'],
                ]
            ]);
            return $ec2Client;
        /*}catch(Exception $e){
            throw new Exception('listVPCs caused fatal error');
        }*/
        
    }
    
}



?>