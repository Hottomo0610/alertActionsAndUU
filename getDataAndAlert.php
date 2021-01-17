<?php

require_once __DIR__ . '/vendor/autoload.php';
const API_URL = "https://hooks.slack.com/services/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";


$analytics = initializeAnalytics();
$response = getReport($analytics);
$dataArray = printResults($response);
$UUdata = convertData($dataArray);
/*$textArray = makeTextAndAccessDB($UUdata);
sendSlack($textArray);*/


function initializeAnalytics()
{
    $KEY_FILE_LOCATION = __DIR__ . '/client_secrets.json';

    $client = new Google_Client();
    $client->setApplicationName("getUU");
    $client->setAuthConfig($KEY_FILE_LOCATION);
    $client->setScopes(['https://www.googleapis.com/auth/analytics.readonly']);
    $analytics = new Google_Service_AnalyticsReporting($client);

    return $analytics;
}

function getReport($analytics)
{
    $currentHour = date("G");

    $VIEW_ID = "67772121";

    if($currentHour == 0){
        $dateRange = new Google_Service_AnalyticsReporting_DateRange();
        $dateRange->setStartDate("yesterday");
        $dateRange->setEndDate("yesterday");
    } else {
        $dateRange = new Google_Service_AnalyticsReporting_DateRange();
        $dateRange->setStartDate("today");
        $dateRange->setEndDate("today");
    }

    $dimensions = new Google_Service_AnalyticsReporting_Dimension();
    $dimensions->setName("ga:dateHour");

    $sessions = new Google_Service_AnalyticsReporting_Metric();
    $sessions->setExpression("ga:newUsers");
    $sessions->setAlias("users");

    $request = new Google_Service_AnalyticsReporting_ReportRequest();
    $request->setViewId($VIEW_ID);
    $request->setDateRanges($dateRange);
    $request->setDimensions($dimensions);
    $request->setMetrics(array($sessions));

    $body = new Google_Service_AnalyticsReporting_GetReportsRequest();
    $body->setReportRequests( array( $request) );
    return $analytics->reports->batchGet( $body );
}

function printResults($response){
    $reports_array = json_decode(json_encode($response), true);

    $dataArray = $reports_array['reports'][0]['data']['rows'];

    return $dataArray;
}

function convertData($dataArray){
    //error_log(print_r($dataArray, true));
    $currentHour = date("G");
    if($currentHour==0){
        $currentHour =24;
    }
    //$currentHour = 24;
    $UUdata = array();
    $numOfUUArray = array();
    $data = array();
    $numOfElements = count($dataArray);

    if ($numOfElements < $currentHour) {
        $get0 = 0;
        for ($i=1; $i<=$numOfElements; $i++) {
            $j = $i - 1;
            if ($i==1) {
                $numOfUUArray[$j] = $dataArray[$j]['metrics'][0]['values'][0];
            }

            $hour1 = $dataArray[$j]['dimensions'][0];
            $hour2 = $dataArray[$i]['dimensions'][0];
            $hour_diff = (int)$hour2 - (int)$hour1;
            if ($hour_diff >1) {
                $numOfUUArray[$i] = "0";
                $get0++;
            } else {
                if ($get0==1) {
                    $numOfUUArray[$i] = $dataArray[$j]['metrics'][0]['values'][0];
                } else {
                    $numOfUUArray[$i] = $dataArray[$i]['metrics'][0]['values'][0];
                }
            }
        }
    } else {
        for ($i=0; $i<$numOfElements; $i++) {
            $numOfUUArray[$i] = $dataArray[$i]['metrics'][0]['values'][0];
        }
    }

    $data['UU'] = $numOfUUArray;
    $data['numOfData'] = $numOfElements;
    //var_dump($numOfUUArray);

    return $data;
}

function makeTextAndAccessDB($data){
    $strlastCountJSON = file_get_contents("/usr/local/alertAction/lastCount.json");
    $arrayLastCount = json_decode($strlastCountJSON , TRUE);
    date_default_timezone_set("Asia/Tokyo");
    $currentDateString = date("Y-m-d");
    $currentHour = date("G");

    if ($currentHour == 0){
        $currentDateString = date("Y-m-d", strtotime("-1 days"));
        $prevTotalMails = $arrayLastCount["mails"][23];
        $prevTotalValidMails = $arrayLastCount["mails valid"][23];
        $prevTotalCalls = $arrayLastCount["calls"][23];
        $prevTotalValidCalls = $arrayLastCount["calls valid"][23];
    } elseif ($currentHour == 1){
        $arrayLastCount = array("UU" => array(), "mails" => array(), "mails valid" => array(),
                                "calls" => array(), "calls valid" => array() );
    }

    $mysqli = new mysqli("133.242.148.73", "hunter", "hunter0705", "cdr");
    mysqli_set_charset($mysqli, "utf8");

    $sqlCalls = "SELECT * FROM cdr.action_call_data_view where left(action_date,10) = '$currentDateString'";
    $sqlMails = "SELECT * FROM cdr.action_mail_conv_view where left(action_date,10) = '$currentDateString'";

    $resultCalls = mysqli_query($mysqli,$sqlCalls);
    $resultCalls = $resultCalls->{"num_rows"};
    $resultMails = mysqli_query($mysqli, $sqlMails);
    $resultMails = $resultMails->{"num_rows"};

    $sqlValidCalls = "SELECT * FROM cdr.action_call_data_view WHERE left(action_date,10)='".$currentDateString."' AND status like '有効'";
    $sqlValidMails = "SELECT * FROM cdr.action_mail_conv_view WHERE left(action_date,10)='".$currentDateString."' AND status like '有効'";

    $resultValidCalls = mysqli_query($mysqli, $sqlValidCalls);
    $resultValidCalls = $resultValidCalls->{"num_rows"};
    $resultValidMails = mysqli_query($mysqli, $sqlValidMails);
    $resultValidMails = $resultValidMails->{"num_rows"};

    if ($currentHour == 0) {
        $arrayLastCount["mails"][23] = $resultMails - $prevTotalMails;
        $arrayLastCount["calls"][23] = $resultCalls - $prevTotalCalls;
        $arrayLastCount["calls valid"][23] = $resultValidCalls - $prevTotalValidCalls;
        $arrayLastCount["mails valid"][23] = $resultValidMails - $prevTotalValidMails;
    } else {
        $arrayLastCount["mails"][$currentHour-1] = $resultMails;
        $arrayLastCount["calls"][$currentHour-1] = $resultCalls;
        $arrayLastCount["calls valid"][$currentHour-1] = $resultValidCalls;
        $arrayLastCount["mails valid"][$currentHour-1] = $resultValidMails;
    }

    $numUU = $data['UU'];

    $arrayLastCount['UU'] = $numUU;

    /*$arrayLastCount2 = array();
    $arrayLastCount2['mails'] = array_column($arrayLastCount, 'mails');
    $arrayLastCount2['calls'] = array_column($arrayLastCount, 'calls');
    $arrayLastCount2['calls valid'] = array_column($arrayLastCount, 'calls valid');
    $arrayLastCount2['mails valid'] = array_column($arrayLastCount, 'mails valid');*/

    if ($currentHour == 0){
        $newArray = array("mails" => [0], "mails valid" => [0], "calls" => [0], "calls valid" => [0]);
        file_put_contents("/usr/local/alertAction/lastCount.json",json_encode($newArray)); 
    }

    file_put_contents("/usr/local/alertAction/lastCount.json",json_encode($arrayLastCount));

    $titleText = ":spiral_calendar_pad:".$currentDateString."の推移    `UU` *|* :telephone_receiver:有効コール/発生コール *|* :email:有効メール/発生メール";

    $textString = "";
    $firstLoop = true;

    foreach($arrayLastCount["calls"] as $key => $value) {
        if($firstLoop == false) {
            $textString = "\n".str_pad($key.":00-".($key + 1).":00 ",30).sprintf("%4s","`".$arrayLastCount["UU"][$key])."` *|* ".
            sprintf("%3s",($arrayLastCount["calls valid"][$key] - $arrayLastCount["calls valid"][$key-1]))
            ."/".sprintf("%3s",($arrayLastCount["calls"][$key] - $arrayLastCount["calls"][$key-1]))." *|* ".
            sprintf("%2s",($arrayLastCount["mails valid"][$key] - $arrayLastCount["mails valid"][$key-1]))
            ."/".sprintf("%2s",($arrayLastCount["mails"][$key] - $arrayLastCount["mails"][$key-1])).$textString;
        } else {
            $textString = "\n".str_pad("0:00-1:00",30).sprintf("%4s","`".$arrayLastCount["UU"][$key])."` *|* ".
            sprintf("%3s",$arrayLastCount["calls valid"][$key])."/".sprintf("%3s",$arrayLastCount["calls"][$key])." *|* ".
            sprintf("%2s",$arrayLastCount["mails valid"][$key])."/".sprintf("%2s",$arrayLastCount["mails"][$key]);
            $firstLoop = false;
        }
    }

    $textArray = array(
        'title'=> $titleText,
        'text' => $textString
    );
   // var_dump($textString);

    return $textArray;
}

function sendSlack($textArray){
    $title = $textArray['title'];
    $value = $textArray['text'];

    $rawJson = "{
        'username' : 'TestCheckPOST',
        'icon_emoji' : 'memo',
        'channel' : 'api_test',
        'attachments':[
            {
                'fallback': '本日の一時間ごとの数値',
                'color': '#36a64f',
                'pretext': '$title',
                'text': '$value'
            }
        ]
    }";

    $ch = curl_init(API_URL);
    curl_setopt( $ch, CURLOPT_POSTFIELDS, $rawJson );
    curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    $result = curl_exec($ch);
    curl_close($ch);
}
