<?php
use Teem\CloudAPI\ec2Data;

$app->get('/test/{name}', function ($request, $response) {
    $name = $request->getAttribute('name');
    $response->getBody()->write("Hello, $name");
    return $response;
});

/*
 * @author Ben Fellows <kiwifellows@gmail.com>
 * @description Display VPCs available for an account
 * @param string RoleArn and region required in json request from client/UI.
 * Example: {"RoleArn": "arn:aws:iam::123456789:role/cloudapi", "region": "us-east-1"}
 *
 */
$app->get('/ping', function ($request, $response, $args) {
  echo "pong";
});

$app->post('/vpc/list', function ($request, $response, $args) {
    $ec2Data=new ec2Data(array('iam'=>$this->iam));
    $data = $request->getParsedBody();
    try{
        $jmesPath='Vpcs[].{ID: VpcId, IPRange: CidrBlock}';
        $task="describeVpcs";
        $result=$ec2Data->runTask($data['RoleArn'], $data['region'], "", $task, $jmesPath);
        return $this->response->withJson($result); 
    }catch(Exception $e){
        $errmsg['status']="error";
        $errmsg['message']="Error in listSubnets ".$e.message;
        return $this->response->withJson($errmsg);
    }
});

$app->post('/vpc/listSubnets', function ($request, $response, $args) {
    $ec2Data=new ec2Data(array('iam'=>$this->iam));
    $data = $request->getParsedBody();
    try{
        $jmesPath='Subnets[].{ID: SubnetId, IPRange: CidrBlock, VpcId: VpcId, AvailabilityZone: AvailabilityZone, Tags: Tags[*]}';
        $task="describeSubnets";
        $result=$ec2Data->runTask($data['RoleArn'], $data['region'], "", $task, $jmesPath);
        return $this->response->withJson($result); 
    }catch(Exception $e){
        $errmsg['status']="error";
        $errmsg['message']="Error in listSubnets ".$e.message;
        return $this->response->withJson($errmsg);
    }
});

$app->post('/vpc/listSGs', function ($request, $response, $args) {
    $ec2Data=new ec2Data(array('iam'=>$this->iam));
    $data = $request->getParsedBody();
    try{
        $jmesPath='SecurityGroups[].{ID: GroupId, Description: Description, VpcId: VpcId, Name: GroupName, Tags: Tags[*]}';
        $task="describeSecurityGroups";
        $result=$ec2Data->runTask($data['RoleArn'], $data['region'], "", $task, $jmesPath);
        return $this->response->withJson($result); 
    }catch(Exception $e){
        $errmsg['status']="error";
        $errmsg['message']="Error in listSGs ".$e.message;
        return $this->response->withJson($errmsg);
    }
});


/*
 * @author Ben Fellows <kiwifellows@gmail.com>
 * @description Run an EC2 specific Task
 * @param string RoleArn, region, task and optional params required in json request from client/UI.
 * Example:
 * POST /ec2/DescribeInstances
 * {"RoleArn": "arn:aws:iam::123456789:role/cloudapi", "region": "us-east-1", "params": {...} }
 *
 */
$app->post('/ec2/{task}', function ($request, $response, $args) {
    $ec2Data=new ec2Data(array('iam'=>$this->iam));
    $task = $args['task'];
    $data = $request->getParsedBody();

    try{
        $result=$ec2Data->runTask($data['RoleArn'], $data['region'], $data['params'], $task);
        return $this->response->withJson($result); 
    }catch(Exception $e){
        $errmsg['status']="error";
        $errmsg['message']="Error in ec2Task: ". $e.message;
        return $this->response->withJson($errmsg);
    }

});

$app->options('/{routes:.+}', function ($request, $response, $args) {
    return $response;
});

?>
