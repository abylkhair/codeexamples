<?php

namespace App\Http\Controllers;


use App\KiasAttachmentProxy;
use App\Library\Services\KiasServiceInterface,
    Illuminate\Http\Request,
    Illuminate\Support\Facades\Auth,
    PhpOffice\PhpSpreadsheet\Spreadsheet,
    PhpOffice\PhpSpreadsheet\Writer\Xlsx,
    App\Attachment;
use App\Mail\RequestMail;
use App\TblForPayeds;
use App\TblForPayRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Imagick;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use SimpleSoftwareIO\QrCode\Image;
use Illuminate\Support\Str;

class AldController extends Controller
{

    public function index(KiasServiceInterface $kias)
    {
        $kiasOrders = $kias->getAgreementByIin(Auth::user()['iin'], 0);
        $response = [];
//        foreach ($kiasOrders->ROWSET->row as $orders) {
//            if ($orders->AgrProductName != "ОС ГПО ВТС"){
//                dd();
//            }
//        }
            return view('cabinet.insuranceEvents');

    }

public function getAgrClaims(Request $request, KiasServiceInterface $kias)
    {
        $iin = Auth::user()->iin;
//        $iin = 1445911;
        $checked = $request->checked;
        if ($request->checked!=true) {
            $checked = 1;
        }
        else if ($request->checked==true){
            $checked = 0;
        }
        $isn = Auth::user()->isn;
//        $isn = 3649598;
        //dd($isn);
//        $isn = 1445911;  //3649598
        $regno = null;
        $cNumber = null;
        $cIsn = null;
        $success = false;

        if ($request->type === 'grnz') {
            $cNumber = null;
            $regno = $request->data;
        } else if ($request->type === 'cNumber') {
            $cNumber = $request->data;
            $results = $kias->getAgreementByCarOrAgr($cNumber, null, null);
            foreach ($results as $result) {
                if (isset($result->Agreement->row->ISN)) {
                    $cIsn = (string)$result->Agreement->row->ISN;
                } else {
                    return response()->json([
                        'success' => false,
                        'result' => "Данные не найдены",
                    ]);
                }
            }
            $regno = null;

        }
        $ind = 0;
        $ogpoVts = $isn == 735867 ? 0 : 1;
        $result = $kias->getAgrClaims($isn, $regno, $cIsn, $ogpoVts,$checked);
        if (!isset($result->row)) {
            $success = false;
            $error = "Данные не найдены";
        } else {
            foreach ($result as $res) {
//                $result->row[$ind]->DateClaim = isset($result->row[$ind]->DateClaim)?date('d.m.Y', strtotime((string)$result->row[$ind]->DateClaim)):"";
                $ind++;
            }
            $success = true;
        }

        return response()->json([
            'success' => $success,
            'result' => $success ? $result : $error,
        ]);
    }

    public function createDisclaimer(Request $request)
    {
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load(storage_path('app/insurance_case_docs/templates/declaration_dissent.xlsx'));

        //Изменения
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('W9', $request->data['fullName']);
        $sheet->setCellValue('Z10', $request->data['phone']);
        $sheet->setCellValue('Z11', $request->data['email']);
        $sheet->setCellValue('A14', $request->data['comment']);
        $sheet->setCellValue('A4', date("d.m.y"));
        $sheet->setCellValue('W34', date("d.m.y"));


        //Вставка в файл
        $writer = new Xlsx($spreadsheet);
        $attachment = new Attachment();

        $iin = Auth::user()['iin'];
        $writer->save(storage_path('app/public/insurance_case_docs/results/' . $iin . '_declaration_dissent.xlsx'));
        $path = 'public/insurance_case_docs/results/' . $iin . '_declaration_dissent.xlsx';

        $attachment->insurance_case_id = session('caseId');
        $attachment->filename = $path;
        $attachment->type = 'declarationDissent';

        $attachment->save();

        if (!isset($request->data)) {
            $success = false;
            $error = "Данные не найдены";
        } else {
            $success = true;
        }
        $data = array('name'=>"Отправка почты");
        $emails = array("AKolobaev@cic.kz", "AKassimov@cic.kz","AMurzagildin@cic.kz","ABoyarchuk@cic.kz","YRakhimzhanov@cic.kz");
        Mail::send('mail', $data, function($message) use ($emails, $iin) {
            $message->to($emails, 'Tutorials Point')->subject
            ('Отправка несогласия с Купиполиса');
            $message->attach(storage_path('app/public/insurance_case_docs/results/'.$iin.'_declaration_dissent.xlsx'));
            $message->from('kupipolis@cic.kz','Купиполис');
        });
//        $attachment->save();
//        $sendRequest = new \stdClass();
//        $sendRequest->sender = 'abylkhair@mail.ru';
//        Mail::to('abylkhair@mail.ru')->send(new RequestMail($sendRequest));

        return response()->json([
            'success' => $success,
            'result' => $success ? $request->data : $error,
        ]);



    }


    public function createInsurancePayment(Request $request, KiasServiceInterface $kias)
    {
//        $payout = DB::select('select * from tbl_for_payeds where id = 6');
//        dd($payout[0]->id);
//        dd($request->refundisn);
        $iin = Auth::user()->iin;
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load(storage_path('app/public/insurance_case_docs/templates/insurance_payment.xlsx'));
        //Изменения
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setCellValue('Y6',Auth::user()->first_name);//ot kogo
        $sheet->setCellValue('M7',Auth::user()->address);//adres prozhivaniya
        $sheet->setCellValue('AF8',Auth::user()->phone);//telefon
        $sheet->setCellValue('J29',Auth::user()->first_name);//fio
        $sheet->setCellValue('J31', $iin);//rnn-empty need to delete
        $sheet->setCellValue('J33', $request->data['requisites']);
        $sheet->setCellValue('J37', $request->data['bankName']);
        $sheet->setCellValue('J39', '');//rnn-bank need delete
        $sheet->setCellValue('J41', $request->data['bankBin']);//bankbin
        $sheet->setCellValue('J43', '');//kbe need delete
        $sheet->setCellValue('AE47', $request->data['date']);//data zapolneniya
        $sheet->setCellValue('AD18', $request->refundsum);//summa'


        //Вставка в файл
        $writer = new Xlsx($spreadsheet);
        $attachment = new Attachment();

        $writer->save(storage_path('app/public/insurance_case_docs/results/' . $iin . '_insurance_payment.xlsx'));
        $path = 'public/insurance_case_docs/results/' . $iin . '_insurance_payment.xlsx';

        $attachment->insurance_case_id = session('caseId');
        $attachment->filename = $path;
        $attachment->type = 'insurancePayment';

        $attachment->save();
        $iin = Auth::user()['iin'];
        $subjIsn = (string)$kias->getSubject($iin)->ISN;

        $file = Storage::get($path);
        $base64String = base64_encode($file);
        //Making a Plea isn for uploading attachments
        $saveDoc = $kias->createDocument(481911,$request->refundisn,$request->refundid,$subjIsn);
        $results = $kias->saveAttachment(
            (String)$saveDoc->DocISN,
            basename($path),
            $base64String,
            'D'
        );

        $tblForEds = new TblForPayeds();
        $tblForEds->isn = $results->ISN;
        $tblForEds->plea = $saveDoc->DocISN;
        $tblForEds->iin = Auth::user()->iin;
        $tblForEds->name = Auth::user()->first_name;
        $tblForEds->refundisn = $request->refundisn;
        $tblForEds->refundid = $request->refundid;
        $tblForEds->signed = '0';
        $tblForEds->filetype = '0';
        $tblForEds->save();

        return response()->json([
            'result' => isset($results->ISN) ? (string)$results->ISN : '',
            'saveDoc'=>$saveDoc->DocISN,
            'base64String'=>$base64String,
            'success' => $request->data,
        ]);


    }
    public function getAttachments(Request $request, KiasServiceInterface $kias){
//        dd($request->docIsn);
        $response = $kias->getAttachmentsList($request->docIsn);
        $attachments = [];
        if($response->error){
            $result = [
                'success' => false,
                'error' => (string)$response->error->text,
            ];
            return response()->json($result)->withCallback($request->input('callback'));
        }
        if(isset($response->LIST->row)){
            foreach ($response->LIST->row as $row) {
                    array_push($attachments, [
                        'URL' => "/getCalculation/{$row->ISN}/{$row->REFISN}/{$row->PICTTYPE}",
                        'FileName' => (string)$row->FILENAME,
                    ]);
                }
        }
        $result = [
            'success' => true,
            'error' => "",
            'attachments' => $attachments,
        ];
        return response()->json($result)->withCallback($request->input('callback'));

//          return Redirect::to('http://heera.it');
//           echo redirect('http://127.0.0.1:8000/getCalculation/3758626/36213523/D');
    }
    public function getAttachment($ISN, $REFISN, $PICTTYPE, KiasServiceInterface $kias){
        $attachment = $kias->getAttachmentData($REFISN, $ISN, $PICTTYPE);
        if (isset($attachment->FILEDATA, $attachment->FILENAME)) {
            $decoded = base64_decode((string)$attachment->FILEDATA);

            $str = str_replace('\\', '/', (string)$attachment->FILENAME);
            $pathinfo = pathinfo($str);

            header('Content-Description: File Transfer');
            header('Charset: UTF-8');
            header('Content-Type: application/'.$pathinfo['extension']);
            header('Content-Disposition: inline; filename="'.$pathinfo['basename'].'"');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            echo $decoded;
        }
    }
    public function saveAttachment(Request $request,  KiasServiceInterface $kias){
//        dd($request->isn);
//        dd('kek');
//        dd($request->DocISN);
//        dd($request->requestType);
        try{
            $success = true;
            if($request->fileType == 'base64'){
                $file = $request->file;
                $extension = isset($request->fileExt) ? $request->fileExt : '';
                $filename = 'signed_'.$request->id.'_'.Auth::user()->email.'.'.$extension;  //.mt_rand(1000000, 9999999);
            } else {
            }
//            dd($request->isn);
            $results = $kias->saveAttachment(
                $request->isn,
                $filename,
                $file,
                'D',
                $request->requestType
            );
            //file type = 1 sig.0=excel
            $tblForEds = new TblForPayeds();
            $tblForEds->isn = $results->ISN;
            $tblForEds->plea = $request->isn;
            $tblForEds->iin = Auth::user()->iin;
            $tblForEds->name = Auth::user()->first_name;
            $tblForEds->signed = '0';
            $tblForEds->filetype = '1';
            $tblForEds->save();

            if(isset($results->error)){
                $success = false;
                $error = 'Ошибка загрузки файла, обратитесь к системному администратору';  //(string)$results->error->text
            }

            return response()->json([
                'success' => $success,
                'error' => isset($error) ? $error : '',
                'result' => isset($results->ISN) ? (string)$results->ISN : ''
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'result' => $e->getMessage()
            ]);
        }
    }
    //getting file from storage and make him base64

    public function createDocument(Request $request, KiasServiceInterface $kias){
//        $file = (storage_path('app\public\insurance_case_docs\results\140641025634_zayvlenie_na_ocenky.xlsx'));
//        dd($file);
//        $filee = base64_encode(file_get_contents($file));
//        dd($filee);
        $iin = Auth::user()['iin'];
        $subjIsn = (string)$kias->getSubject($iin)->ISN;
//        dd($subjIsn);
        $saveDoc = $kias->createDocument(481911,$request->refundisn,$request->refundid,$subjIsn);          // 481911 - Заявление на оценку (ЭЦП)
        if(isset($saveDoc->error)){
            $success = false;
            $error = (string)$saveDoc->error->fulltext;
        } else {
            $success = true;
        }
        return response()->json([
            'success' => $success,
            'error' => isset($error) ? $error : '',
            'saveDoc'=>$saveDoc,
        ]);
    }
    public function saveAttachmentRequest(Request $request,  KiasServiceInterface $kias){
//        dd($request->fileExt);
//        dd($request->isn);
//        dd('kek');
//        dd($request->DocISN);
//        dd($ request->requestType);
        try{
            $success = true;
            if($request->fileType == 'base64'){
                $file = $request->file;
                $extension = isset($request->fileExt) ? $request->fileExt : '';
                $filename = 'signed_'.$request->id.'_'.Auth::user()->email.'.'.$extension;  //.mt_rand(1000000, 9999999);
            } else {
            }
//            dd($request->isn);
            $results = $kias->saveAttachment(
                $request->isn,
                $filename,
                $file,
                'D',
                $request->requestType
            );
            //file type = 1 sig.0=excel
            $tblForPayRequest = new TblForPayRequest();
            $tblForPayRequest->isn = $results->ISN;
            $tblForPayRequest->plea = $request->isn;
            $tblForPayRequest->iin = Auth::user()->iin;
            $tblForPayRequest->name = Auth::user()->first_name;
            $tblForPayRequest->signed = '0';
            $tblForPayRequest->filetype = '1';
            $tblForPayRequest->save();

            if(isset($results->error)){
                $success = false;
                $error = 'Ошибка загрузки файла, обратитесь к системному администратору';  //(string)$results->error->text
            }
            return response()->json([
                'success' => $success,
                'error' => isset($error) ? $error : '',
                'result' => isset($results->ISN) ? (string)$results->ISN : ''
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'result' => $e->getMessage()
            ]);
        }
    }
}
