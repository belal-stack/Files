<?php

namespace App\Http\Controllers\Api\Bitrix;

use App\Http\Controllers\Controller;
use App\Mail\SendMail;
use App\Models\Automation\AwaitingDataHub;
use App\Models\Automation\AwaitingInvoice;
use App\Models\Invoice;
use App\Services\Automation\CreatingOfferService;
use App\Services\Bitrix\BitrixServiceTimelineService;
use App\Services\Communications\TwilioSmsService;
use App\Services\SendMails\SendMailsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Twilio\Exceptions\RestException;
use Illuminate\Support\Facades\Validator;

class BitrixAutomationController extends Controller
{

    /**
     * Method for sending an email through the API
     *
     * @param Request $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     */
    public function sendMail(Request $request)
    {
        $usage = "Use format: ?to=email&name=name&subject=subject&message=content";
        $recipient = $request->to ?? null;
        $subject = $request->subject ?? null;
        $message = $request->message ?? null;
        $id = $request->id ?? null; // to be used for sending an update to timeline for object

        if ($recipient === null || !preg_match("/^([a-z0-9\+_\-]+)(\.[a-z0-9\+_\-]+)*@([a-z0-9\-]+\.)+[a-z]{2,6}$/ix",
                $recipient)) {
            Log::warning("EMAIL: Missing recipient or mail-formed mail. $usage");
            return response("Missing recipient or mal-formed mail-address", 400);
        }
        if ($subject === null) {
            Log::warning("EMAIL: Missing subject. $usage");
            return response("Missing subject line", 400);
        }
        if ($message === null) {
            Log::warning("EMAIL: Missing message content. $usage");
            return response("Missing message", 400);
        }
        if ($id === null) {
            Log::info("EMAIL: Missing id");
        }
        if ($id > 3) {
            Log::info("EMAIL: Missing id");
            return response("ID not found in template", 400);
        }

        $sendMailService = new SendMailsService();
        $mail = [
            'to' => $recipient,
            'from' => 'inbound@billige-el-priser.dk',
            'subject' => $subject,
            'content' => $message,
        ];
        $res = $sendMailService->sendMail($mail);

        Log::info("EMAIL: Mail status $res sent to $recipient with subject: $subject and message: " . $message);

        if ($id !== null) {
            $timeline = new BitrixServiceTimelineService();
            $timeline->add(
                "Mail sent to $recipient with subject: $subject and message:\n\n$message",
                $id
            );
        }

        $sendmail = Log::Mailto($mail['to'])->send(new SendMail($subject, $recipient, $message,$id)); // id which is template id
        if (empty($sendmail)) {
            return response()->json(['message' => 'Mail Sent'], 200);
        } else {
            return response()->json(['message' => 'Mail Sent fail'], 400);
        }


    }

    /**
     * Method sends an SMS through the Twilio SDK.
     *
     * @param Request $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response
     */
    public function sendSms(Request $request)
    {
        $recipient = $request->to ?? null;
        $message = $request->message ?? null;
        $id = $request->id ?? null;

        if ($recipient === null) {
            Log::warning("SMS: Missing recipient. Use format: ?to=phonenumber&message=message");
            return response("Missing recipient", 400);
        }
        if ($message === null) {
            Log::warning("SMS: Missing message. Use format: ?to=phonenumber&message=message");
            return response("Missing message", 400);
        }
        if ($id === null) {
            Log::info("SMS: Missing id");
        }

        $twilio = new TwilioSmsService();
        $validated = $twilio->validateNumber($recipient);

        try {
            $twilio->sendMessage($message, $validated->phoneNumber);
        } catch (RestException $restException) {
            Log::warning("SMS: Error from Twilio", [
                'error' => $restException->getMessage(),
                'code' => $restException->getCode(),
            ]);
            return response("Twilio error " . $validated->phoneNumber, 400);
        }
        Log::info("SMS '" . $message . "' Sent to " . $validated->phoneNumber);

        if ($id !== null) {
            $timeline = new BitrixServiceTimelineService();
            $timeline->add("SMS sent to $recipient: $message", $id);
        }

        return response("SMS Sent", 200);
    }

    public function receive_request(Request $request)
    {
        Log::info("Receive request. ", [
            'content' => $request->getContent(),
            'type' => $request->getContentType(),
        ]);

        return response("Ok.", 200);
    }

    public function createOffer(Request $request)
    {
        try {

            $validator = Validator::make($request->all(), [
                'id' => 'required',
            ]);

            // validating the deal id where exists or not in the request
            if ($validator->fails()) {
                return response("Deal id required, parameter should 'id'", 400);
            }
            $dealId = $request->input("id");
            $offerService = new CreatingOfferService();
            $isOfferCreated = $offerService->createOffer(Invoice::class, $dealId); // creating offer

            if ($isOfferCreated) {
                return response("Offer created successfully.", 200);
            } else {
                return response("Internal Server error.", 500);
            }

        } catch (\Exception $exception) {
            Log::info("createOffer(): API error" . $exception);

        }
        return response("Internal Server error", 500);
    }
}

