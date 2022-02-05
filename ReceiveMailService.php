<?php
/**
 * Created by PhpStorm.
 * User: Najmul<dev.najmul@gmail.com>
 * Date: 8/11/2020
 * Time: 11:10 PM
 */

namespace App\Services\Automation;


use App\Models\Automation\AwaitingContractSignature;
use App\Models\Automation\AwaitingInvoice;
use App\Models\Mails\InboundMail;
use App\Models\Mails\MailAttachment;

use Illuminate\Support\Facades\Log;
use Webklex\IMAP\Facades\Client;

class ReceiveMailService {

    private $oClient;
    private $hasConnection;

    /**
     * ReceiveMailService constructor.
     */
    public function __construct () {
        try {
            // Account should be specified, which email domain will use
            $this->oClient = Client::account ('gmail');
            //Connect to the IMAP Server
            $this->oClient->connect ();
            // set custom attachment mast for extending required features
            $this->oClient->setDefaultAttachmentMask (CustomAttachmentMask::class);
            $this->hasConnection = true;
        } catch (\Exception $exception) {
            Log::warning ("ReceiveMailService::_construct\n\nConnection failed.\n\n", ['exception' => $exception]);
            $this->hasConnection = false;
        }

    }

    /**
     * Handle reading mails from server
     *
     */
    public function readMailFromServer () {

        try {
            // test for connection existence
            if (!$this->hasConnection) {
                echo "Connection was not established. Ending.\n";
                return false;
            }

            //Get all Mailboxes


            $aFolder = $this->oClient->getFolders ();
            $count = 0;
            foreach ($aFolder as $oFolder) {

                //Get all unread Messages of the current Mailbox $oFolder

                $aMessage = $oFolder->messages ()->unseen ()->leaveUnread ()->get ();


                foreach ($aMessage as $uid => $oMessage) {
                    $aAttachments = $oMessage->getAttachments ();
                    // only execute when attachment exists in the mail and make sure already stored in db
                    if (count ($aAttachments) && !InboundMail::where ('uid', $uid)->exists ()) { // to ignore same email attachment
                        echo "Storing... mail: " . ++$count . "\n";
                        $this->storeMailToDatabase ($uid, $oMessage, $aAttachments);
                    }

                }

            }
            if (!$count) {
                echo "No new attached mail found!!!\n";
                Log::info ("No new attached mail found.");
                return false;

            } else {
                echo "Total " . $count . " attachments emails found\n";
                return $count;
            }
        } catch (\Exception $exception) {
            Log::warning ("readMailFromServer():\n\n Reading failed from server\n\n", [
                'exception_message' => $exception->getMessage(),
                'exception_line' => $exception->getLine(),
                'exception_file' => $exception->getFile(),
                'exception_trace' => $exception->getTrace(),
                'exception_code' => $exception->getCode(),
            ]);

            $time = microtime(true) - $_SERVER["REQUEST_TIME_FLOAT"];
            Log::info("Time Duration ". $time);
            return false;
        }

    }

    /**
     * @param $uid
     * @param $oMessage
     * @param $aAttachments
     */
    private function storeMailToDatabase ($uid, $oMessage, $aAttachments) {
        try {

                // only execute when attachment exists in the mail
                $from = $oMessage->getSender();
                $to = $oMessage->getTo();

                $inboundMail = InboundMail::create([
                    "uid" => $uid,
                    'subject' => $oMessage->getSubject(),
                    'body' => $oMessage->getHTMLBody(true),
                    'from' => $from[0]->mail,
                    'to' => $to[0]->mail,
                ]);


            // store attachments, even if exists multiples
            $aAttachments->each (function ($oAttachment) use ($inboundMail) {
                $masked_attachment = $oAttachment->mask ();
                $masked_attachment->setFileName ();
                $masked_attachment->custom_save ();

                MailAttachment::create ([
                    'inbound_mail_id' => $inboundMail->id,
                    'filename' => $masked_attachment->getFileName()
                ]);
            });

//            $partner='@billing-el-prises.dk';
//            $checkAwaitingInvoice= AwaitingInvoice::where('from',$from)
//                ->orwhere ('from', 'LIKE', "%" . $partner . "%")
//                ->get();
//            $checkAwaitingContractSignature= AwaitingContractSignature::where('from',$from)
//                ->orwhere ('from', 'LIKE', "%" . $partner . "%")
//                ->get();
//
//            //checking records if they are not existing in models then bitrix will be created and vice versa
//            if ($checkAwaitingInvoice->isEmpty() && $checkAwaitingContractSignature->isEmpty()){
//
//                $creatingDealService = new CreatingDealService();
//                $name = "John Doe";
//                $mail = $from;
//                $phone = "29912150";
//
//                $dealId = $creatingDealService->add ($name, $mail, $phone);
//                print ("Created deal in bitrix with $dealId");
//
//            }

            // Mark as read after reading the mail, when attachment exists only
            $oMessage->setFlag ('seen');


        }catch (\Exception $exception){
            Log::info("storeMailToDatabase(): Storing failed into DB". $exception);

        }
    }
}

