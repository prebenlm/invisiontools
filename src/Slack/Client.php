<?php
namespace Slack;

/**
 * https://api.slack.com/incoming-webhooks
 **/

class Client
{
    protected $url = "https://hooks.slack.com/services/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";
    protected $username;
    protected $emoji;

    public function __construct($username="TU", $emoji=":tu:")
    {
        ini_set("default_socket_timeout", 5);
        $this->setUsername($username);
        $this->setEmoji($emoji);
    }

    public function setUrl($url)
    {
        $this->url = $url;
    }

    public function setUsername($username)
    {
        $this->username = $username;
    }

    public function setEmoji($emoji)
    {
        $this->emoji = $emoji;
    }

    /** Post as incoming webhook **/
    public function post($channel, $message, $attachments=null)
    {
        $data = [
            "username" => $this->username,
            "channel" => $channel,
            "text" => $message,
            "icon_emoji" => $this->emoji,
            "attachments" => $attachments
        ];
        return $this->request($this->url, $data);
    }
    
    /** Post delayed response to slash command **/
    public function respond($url, $message, $attachments=null, $type="in_channel")
    {
        $data = [
            "response_type" => $type,
            "text" => $message,
            "attachments" => $attachments
        ];
        return $this->request($url, $data);
    }

    private function request($url, $data)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json'
        ]);
        $response = json_encode(curl_exec($ch), true);
        curl_close($ch);
        return $response;
    }
}
