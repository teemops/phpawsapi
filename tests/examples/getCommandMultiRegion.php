<?php
/*
you can run this in the cli as follows
# php getCommandMultiRegion.php
*/
var_dump($argv);
$jmesPath=(isset($argv[1]) ? $jmesPath=$argv[1] : $jmesPath='');
//$jmesPath='Reservations[].Instances[].{ID: InstanceId, Hostname: PrivateDnsName, Launched: LaunchTime, Tags: Tags, AvailabilityZone: Placement.AvailabilityZone}';

date_default_timezone_set('Pacific/Auckland');

require __DIR__ . '/../../vendor/autoload.php';

use \Aws\Ec2\Ec2Client;
use \Aws\MultiRegionClient;
use Aws\Exception\AwsException;
use Aws\CommandPool;
use Aws\CommandInterface;
use Aws\ResultInterface;
use GuzzleHttp\Promise\PromiseInterface;

//non STS role
$ec2Client= new Ec2Client([
    'version' => 'latest',
    'region'  => 'us-east-1'
]);

//get all regions matching wildcard e.g. * or ap-south* or us*
$regions=$ec2Client->getIterator(
    'DescribeRegions', 
    [
        'Filters' => [
            [
                'Name' => 'region-name',
                'Values' => ['ap*']
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
    $regionclient = new Ec2Client([
        'version' => 'latest',
        'region' => $region['RegionName']
    ]);

    echo("--------------------------------------------\n");
    echo("---------START REGION COMMANDS-------\n");
    echo("--------------------------------------------\n");
    echo("Region: ".$region['RegionName']."\n");
    echo("Endpoint: ".$region['Endpoint']."\n");

    //$ec2Client->getCommand($task, $params);
    array_push(
        $commands, 
        $regionclient->getCommand(
            'DescribeVpcs'
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
        global $requestKeys;
        echo "About to send {$iterKey}: "
            . print_r($cmd->toArray(), true) . "\n";
            //add request to array so we have region name
            
    },
    // Invoke this function for each successful transfer.
    'fulfilled' => function (
        ResultInterface $taskOutput,
        $iterKey,
        PromiseInterface $aggregatePromise
    ) {
        //echo "Completed {$iterKey}: {$result}\n";
        global $outputResults, $jmesPath;
        if($jmesPath!=""){
            $awsJMSResult=$taskOutput->search($jmesPath);
            $result=[
                'result'=>$awsJMSResult
            ];
            $result=$awsJMSResult;
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
        $outputResults['MultiRegionResults'][$iterKey]['result']=$result;
        //array_push($outputResults, $result);

    },
    // Invoke this function for each failed transfer.
    'rejected' => function (
        $reason,
        $iterKey,
        PromiseInterface $aggregatePromise
    ) {
        echo "Failed {$iterKey}: {$reason}\n";
    },
]);

// Initiate the pool transfers
$promise = $pool->promise();

// Force the pool to complete synchronously
$promise->wait();

// Or you can chain then calls off of the pool
//$promise->then(function() { echo "Done\n"; });

//put a result wrapper around all results... which effectively completes the merge of data for display

print_r($outputResults);
print(json_encode($outputResults));
?>