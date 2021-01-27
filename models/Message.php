<?php

namespace models;

use classes\{DB};

class Message {
    private $db,
    $id,
    $message_sender,
    $message_receiver,
    $message,
    $message_date = '',
    $recipient_id,
    $is_read = false;
    
    public function __construct() {
        $this->db = DB::getInstance();
    }

    public function get_property($propertyName) {
        return $this->$propertyName;
    }
    
    public function set_property($propertyName, $propertyValue) {
        $this->$propertyName = $propertyValue;
    }

    public function set_data($data = array()) {
        $this->message_sender = $data["sender"];
        $this->message_receiver = $data["receiver"];
        $this->message = $data["message"];
        $this->message_date = $data["message_date"];
    }

    public function get_message($property, $value) {
        $this->db->query("SELECT * FROM `message` WHERE `$property` = ?", array($value));

        if($this->db->count() > 0) {
            $fetched_message = $this->db->results()[0];

            $this->id = $fetched_message->id;
            $this->message_sender = $fetched_message->message_creator;
            $this->message = $fetched_message->message;
            $this->message_date = $fetched_message->create_date;

            return true;
        }

        return false;
    }

    public function add() {
        /*
            When we add a message to message table we need also to insert a row to recipient table to specify the receiver
            of that message
        */

        $this->db->query("INSERT INTO `message` 
        (`message_creator`, `message`, `create_date`) 
        VALUES (?, ?, ?)", array(
            $this->message_sender,
            $this->message,
            $this->message_date
        ));

        // GET LAST MESSAGE ID TO GIVE IT TO RECIPIENT TABLE
        $last_inserted_message_id = $this->db->pdo()->lastInsertId();

        // Store the message in the channel to fetch it by long polling
        $this->db->query("INSERT INTO `channel` 
        (`sender`, `receiver`, `group_recipient_id`, `message_id`) 
        VALUES (?, ?, ?, ?)", array(
            $this->message_sender,
            $this->message_receiver,
            null,
            $last_inserted_message_id
        ));

        $this->db->query("INSERT INTO `message_recipient` 
        (`receiver_id`, `message_id`, `is_read`) 
        VALUES (?, ?, ?)", array(
            $this->message_receiver,
            $last_inserted_message_id,
            $this->is_read
        ));

        return $this->db->error() == false ? true : false;
    }

    public static function dump_channel($sender, $receiver, $message_id) {
        DB::getInstance()->query("DELETE FROM channel WHERE sender = ? AND receiver = ? AND message_id = ?", array($sender, $receiver, $message_id));
    }

    public static function getMessages($sender, $receiver) {
        //$this->db->query("SELECT * FROM `message_recipient` WHERE receiver_id = ? AND message.message_creator = ? ");
        DB::getInstance()->query("SELECT * FROM `message_recipient`
        INNER JOIN `message` 
        ON message.id = message_recipient.message_id 
        WHERE message_recipient.receiver_id = ? AND message.message_creator = ?", array(
            $receiver,
            $sender
        ));

        return DB::getInstance()->results();
    }

    public function jsonSerialize()
    {
        $vars = get_object_vars($this);
        return $vars;
    }
}