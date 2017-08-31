# Teemops AWS PHP API Demonstration

This a demonstration of using the AWS SDK with PHP.

Get up and running quickly

``` bash
#STEP 0: install composer as pre-requisite (if not already installed)
sudo curl -sS https://getcomposer.org/installer | php

#Step 1: clone from github
git clone https://github.com/teemops/phpawsapi.git

#Step 2: install dependencies
cd phpawspi
php composer.phar install

#Step 3: run
cd public
php -S localhost:8081
```

## What is it designed to do?

This API can be extended to:
-Abstract EC2 data
-Abstract VPC information such as subnets, security groups
-Abstract other stuff as required
-Generally used for data such as drop down lists in UIs where the data does
not need to be made immediately available after a page load, but
required when someone focuses on a control such as a Drop down list (e.g
a list of VPCs in the AWS Launch Configs screen).
-Future work will be on multi-account management, provisioning across accounts and regions.

##Why?##
At the moment this particular piece just demonstrates using an API to query and make AWS VPC/EC2 information more readable and do some simple EC2 tasks such as
launch instances.

Having a central API to control multiple AWS accounts and regions is a lot easier than having multiple aws cli profiles on your local machine.

It also means anyone can create a web based or mobile app or integration that interacts with existing IT operations systems to gather data about EC2 instances, VPCs or launch instances.

Once running an API call can be made to the following fixed endpoints:
* **POST /vpc/list**
* **POST /vpc/listSGs**
* **POST /vpc/listSubnets**

Example as below:
``` json
{
"RoleArn": "arn:aws:iam::<AWS_AccountId>:role/<some_role_name>", 
"region": "ap-southeast-2"
}
```

Another example below shows how you can get list the VPCs across all regions using a wildcard.

``` json
{
"RoleArn": "arn:aws:iam::<AWS_AccountId>:role/<some_role_name>", 
"region": "*"
}
```

An call can be made using any EC2 CLI Command (See http://docs.aws.amazon.com/aws-sdk-php/v3/api/api-ec2-2016-11-15.html )
* **POST /ec2/<Command>**
Example request is:
POST /ec2/runInstances

``` json
{
    "RoleArn": "arn:aws:iam::<AWS_AccountId>:role/<some_role_name>", 
    "region": "ap-southeast-2",
    "params": {
        "MinCount": 1,
        "MaxCount": 1,
        "ImageId": "ami-6e833e0e",
        "InstanceType": "t2.nano",
        "SubnetId": "subnet-122323",
        "SecurityGroupIds": ["sg-656761"],
        "UserData": "#!/bin/bash\necho test > somefile.txt",
        "BlockDeviceMappings": [
            {
                "DeviceName": "/dev/sda1",
                "Ebs": {
                "VolumeSize": 50,
                "VolumeType": "gp2"
                }
            }
        ]
    }
}
```

Note: The command must exist as an EC2 only command, not other products in AWS. VPC currently falls under EC2 CLI commands.

## Technology

It's written in PHP and will be provided as a REST interface
Most of the tasks here will be synchronous/blocking calls to AWS
(which differs from some Node JS code which will be released at a different date).

# Setup
PLEASE READ THIS GUIDE CAREFULLY AS IF YOU DON'T SET THESE BITS UP THEN THE API REQUESTS TO AWS WILL FAIL.

## IAM/ AWS Account Pre-requisites
You need to have cross account roles configured in your AWS account.
Where you run this app from will be on an AWS account that can STS/Assume Role to any of your other AWS accounts.
Either locally using AWS Access Keys or on an EC2 instance with IAM Instance Profiles/Roles attached.
http://docs.aws.amazon.com/IAM/latest/UserGuide/tutorial_cross-account-with-roles.html

Or alternatively use the CloudFormation template with this repository which you can modify to use your account ID etc...
This is located under:
cloudformation/iam.exampleonly.cfn.json

The following needs to be changed:
* "AWS": "arn:aws:iam::<awsaccountid>:user/<myusername>" to your username and aws account id
* "sts:ExternalId": "<changeme>" to a private string that no-one else knows.

``` json
"AssumeRolePolicyDocument": {
                    "Statement": [
                        {
                            "Action": [
                                "sts:AssumeRole"
                            ],
                            "Effect": "Allow",
                            "Principal": {
                                "AWS": "arn:aws:iam::<awsaccountid>:user/<myusername>"
                            },
                            "Condition": {
                                "StringEquals": {
                                  "sts:ExternalId": "<changeme>"
                                }
                              }
                        }
                    ]
                },
```

### Settings
You need to change src/settings.php so that the correct sts:ExternalId is used that matches your IAM role, or alternatively no ExternalId which is optional.

``` javascript
'iam'=>[
    'RoleSessionName' => 'cloudapi',
    'ExternalId' => '<changeme>'
]
```

### Install Composer (if you haven't already)
Install Composer
``` bash
curl -sS https://getcomposer.org/installer | php

### Run in this project folder
php composer.phar install
```

Based on Slim PHP Framework
Example:
https://www.slimframework.com/docs/tutorial/first-app.html

### Dev
``` bash
cd public
php -S localhost:8081
```

### Production
Install on a normal LAMP/NGINX setup

Setup NGINX as follows
```
if (!-e $request_filename){
    rewrite ^(.*)$ /index.php break;
}
```