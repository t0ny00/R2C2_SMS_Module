<?php

/* 
 * This example script implements the EnvayaSMS API. 
 *
 * It sends an auto-reply to each incoming message, and sends outgoing SMS
 * that were previously queued by example/send_sms.php .
 *
 * To use this file, set the URL to this file as as the the Server URL in the EnvayaSMS app.
 * The password in the EnvayaSMS app settings must be the same as $PASSWORD in config.php.
 */

require_once dirname(__DIR__)."/config.php";
require_once dirname(__DIR__)."/EnvayaSMS.php";

function multiexplode ($delimiters,$string) {
    
    $ready = str_replace($delimiters, $delimiters[0], $string);
    $launch = explode($delimiters[0], $ready);
    return  $launch;
}

$request = EnvayaSMS::get_request();

header("Content-Type: {$request->get_response_type()}");

if (!$request->is_validated($PASSWORD))
{
    header("HTTP/1.1 403 Forbidden");
    error_log("Invalid password");    
    echo $request->render_error_response("Invalid password");
    return;
}

$action = $request->get_action();

switch ($action->type)
{
    case EnvayaSMS::ACTION_INCOMING:    
        
        // Send an auto-reply for each incoming message.
    
        $type = strtoupper($action->message_type);
    
        error_log("Received $type from {$action->from}");
        error_log(" message: {$action->message}");
        
        $sms_message = $action->message;
        $parsed_message = multiexplode(array(":",","),$sms_message);

        
        
        if (strcmp($parsed_message[0], "ruta") != 0){

            echo $request->render_response(array(
                                            new EnvayaSMS_Event_Log("Server: Message Received "),
                                            new EnvayaSMS_Event_Log("Server: Text doesn't match parser requirements")));        
            return;
        }
        else{
            if (count($parsed_message) != 3){

                echo $request->render_response(array(
                                                new EnvayaSMS_Event_Log("Server: Message Received "),
                                                new EnvayaSMS_Event_Log("Server: Incorrect Number of Parameters")));        
                return;
            }

            
            error_log("Creating JSon request");
            $request_route = array('id' => uniqid(""), 'number' => $action->from, 'from' => $parsed_message[1], 'to' => $parsed_message[2]);
            file_put_contents("$REQUEST_DIR_NAME/{$request_route['id']}.json", json_encode($request_route));

        }


        if ($action->message_type == EnvayaSMS::MESSAGE_TYPE_MMS)
        {
            error_log("Sending reply: {$reply->message}");
            echo $request->render_response(array(
                                                new EnvayaSMS_Event_Log("Server: MMS messages are not supported")));
            return;
        }                       
        
    
        error_log("Sending reply: {$reply->message}");
        echo $request->render_response(array(
                                            new EnvayaSMS_Event_Log("Server: Message Received End")));
        return;
        
    case EnvayaSMS::ACTION_OUTGOING:
        $messages = array();
   
        // In this example implementation, outgoing SMS messages are queued 
        // on the local file system by send_sms.php. 
          
        $dir = opendir($OUTGOING_DIR_NAME);
        while ($file = readdir($dir)) 
        {
            if (preg_match('#\.json$#', $file))
            {
                $data = json_decode(file_get_contents("$OUTGOING_DIR_NAME/$file"), true);
                if ($data)
                {
                    $sms = new EnvayaSMS_OutgoingMessage();
                    $sms->id = $data['id'];
                    $sms->to = $data['to'];
                    $sms->message = $data['message'];
                    $messages[] = $sms;
                }
            }
        }
        closedir($dir);
        
        $events = array();
        
        if ($messages)
        {
            $events[] = new EnvayaSMS_Event_Send($messages);
        }
        
        echo $request->render_response($events);

        return;
        
    case EnvayaSMS::ACTION_SEND_STATUS:
    
        $id = $action->id;
        
        error_log("message $id status: {$action->status}");
        
        // delete file with matching id    
        if (preg_match('#^\w+$#', $id))
        {
            unlink("$OUTGOING_DIR_NAME/$id.json");
        }
        echo $request->render_response();        
        
        return;
    case EnvayaSMS::ACTION_DEVICE_STATUS:
        error_log("device_status = {$action->status}");
        echo $request->render_response();
        return;             
    case EnvayaSMS::ACTION_TEST:
        echo $request->render_response();
        return;                             
    default:
        header("HTTP/1.1 404 Not Found");
        echo $request->render_error_response("The server does not support the requested action.");
        return;
}