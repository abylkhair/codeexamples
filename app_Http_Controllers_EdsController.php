<?php

namespace App\Http\Controllers;

use App\Attachment;
use App\Library\Services\KiasServiceInterface;
use App\TblForPayEds;
use App\TblForPayRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use App\Refund;
use App\User;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use App\Library\Services\SendEmailService;

class EdsController extends Controller
{
    protected $sendEmailService;
    public function __construct(SendEmailService $sendEmailService){
        $this->sendEmailService = $sendEmailService;
    }

    public function edsOD(KiasServiceInterface $kias){
        $od = Refund::where('confirmed',0)->select('rv_isn','id','confirmed','iin','iin_fail','claim_id')->get();
        return view('eds',compact('od'));
    }
//file type = 1 sig.0=excel
    public function edsPO(){
        $po = DB::table('tbl_for_payeds AS pr')
            ->leftJoin('tbl_for_payeds AS pf', 'pf.plea', '=', 'pr.plea')
            ->where('pf.filetype','=','0')
            ->distinct()
            ->select('pr.id as id','pr.isn as isn' ,'pr.full_data as full_data','pr.plea as plea','pr.refundid as refundid','pr.refundisn as refundisn','pr.iin as iin','pf.name as product_family_name', 'pf.isn as product_family_isn')
            ->where('pr.filetype','=','1')
            ->where('pr.confirmed','=','0')
            ->orderBy('pr.isn', 'desc')
            ->get();
        return view('eds-payout',compact('po'));
    }
    public function edsPR(){
//        $kek = var_dump(phpinfo());
//        echo $kek;
//        $pr = TblForPayRequest::where('confirmed', '0')->select('isn','name','date_sign','id','refundisn','refundid','confirmed','plea','iin','iin_fail','filetype')->get();
//        $pr = TblForPayRequest::where([['filetype', '1'], ['confirmed', '0']])->select('isn','name','date_sign','id','refundisn','refundid','confirmed','plea','iin','iin_fail')->get();
//        $pr = DB::table('tbl_for_payrequest AS t')->join('tbl_for_payrequest AS t1', function($join) {
//            $join->on('t.plea', '=', 't1.plea')->on('t1.filetype','=','0');
//        })->where([['t.filetype', '1'], ['t.confirmed', '0']])->select('t.isn','t.name','t.date_sign','t.id','t.refundisn','t.refundid','t.confirmed','t.plea','t.iin','t.iin_fail')->get();
//        $pr2 = TblForPayRequest::where([['filetype', '0'], ['confirmed', '0']])->select('isn')->get();
        $pr = DB::table('tbl_for_payrequest AS pr')
            ->leftJoin('tbl_for_payrequest AS pf', 'pf.plea', '=', 'pr.plea')
            ->where('pf.filetype','=','0')
            ->distinct()
            ->select('pr.id as id','pr.isn as isn' ,'pr.full_data as full_data','pr.plea as plea','pr.refundid as refundid','pr.refundisn as refundisn','pr.iin as iin','pf.name as product_family_name', 'pf.isn as product_family_isn')
            ->where('pr.filetype','=','1')
            ->where('pr.confirmed','=','0')
            ->orderBy('pr.isn', 'desc')
            ->get();
        return view('payment-require',compact('pr'));
    }

    public function signQr(Request $request,KiasServiceInterface $kias){
        $files = [];
        $ISN = 3948353;
        $type = isset($request->type) ? $request->type : '';
        $format = isset($request->edsType) ? $request->edsType : '';
        $refISN = 40475701;
        $refID = isset($request->refID ) ? $request->refID  : '';
        $docClass = isset($request->docClass ) ? $request->docClass  : '';

        $sigFiles = $kias->getAttachmentPath($type,$refID,$format,$docClass,$refISN,$ISN);
        if(isset($sigFiles->error)){
            return response()->json([
                'success' => false,
                'result' => (string)$sigFiles->error->text
            ]);
        } else {
            foreach ($sigFiles->ROWSET->row as $file) {
                $files[] = ['filepath' => (string)$file->FILEPATH, 'docISN' => (string)$file->ISN];
            }
        }
    }
    public function setQr(Request $request, KiasServiceInterface $kias)
    {
//        dd($request->paths);
//        var_dump(count($request->paths));
//        dd($request->info);
//        dd($request);
//        dd($request->info[0]['plea']);
//        $pathh = $request->path;
//        $trimmed = trim($pathh);
        //dd($path);
        $info_client = [];
//        dd($request);
//        $cnt = count($request->paths);
//        dd($cnt);
        $plea = '';
        $isn = '';

//            var_dump($key);
//            dd($cnt);
//            dd($request->info);
            foreach ($request->info as $key => $value) {
                $plea = $value['plea'];
                $full_data = $value['full_data'];
                $isn = $value['isn'];
                $refund = TblForPayRequest::find($value['id']);
                $full_data = $refund->full_data;
                if ($value['confirmed'] == 1) {
                    $refund->confirmed = 1;
                    $refund->signed = 1;
                }
                $kek = 321;
//        $destinationPath=storage_path('app/public/insurance_case_docs/results/123.xlsx');
//        $success = \File::copy($pathh,$destinationPath);
                $repeat = count($request->paths);
//        dd($value['plea']);
//        dd($request);
                foreach ($request->paths as  $allpath) {
                    $file = $allpath;


//        $destination = storage_path('app/public/insurance_case_docs/results/' . $kek .'_insurance_payment.xlsx');
//        Storage::copy($file,$destination);

                    $homepage = file_get_contents(strval($file));
                }

//        dd($homepage);
//        $iin = Auth::user()->iin;
//        dd($iin);
//        $spreadsheet = $path;
                    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
//          dd($kek);

//        $spreadsheet= storage_path($request->path);
//        Изменения
                    $sheet = $spreadsheet->getActiveSheet();
                    //       QR тут костыльным методом ложится

                    $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
//        $qr=QrCode::format('png')->size(300)->generate($info_client);
//        dd($kek);

                    $qr=QrCode::format('png')->size(300)->generate($full_data);
//                    dd($qr);
                    Storage::disk('local')->put($isn.'_payment_request.png', $qr);
//                    $drawing->setImageResource(imagecreatefrompng($qr));
                    $drawing->setPath(storage_path('app/'.$isn.'_payment_request.png')); // put your path and image here
                    $drawing->setOffsetX(110);
                    $drawing->setRotation(0);
                    $drawing->setCoordinates('AE48');
                    $drawing->getShadow()->setVisible(true);
                    $drawing->getShadow()->setDirection(45);
                    $drawing->setWorksheet($spreadsheet->getActiveSheet());
                    $writer = new Xlsx($spreadsheet);
//
                    $writer->save(storage_path('app/public/insurance_case_docs/results/' . $isn . '_insurance_payment.xlsx'));
//        dd('kek');
                    //       QR тут костыльным методом ложится

                    //Вставка в файл
//            $writer = new Xlsx($spreadsheet);
                    $attachment = new Attachment();
                    $writer->save(storage_path('app/public/insurance_case_docs/results/' . $isn . '_insurance_payment.xlsx'));
                    $path = storage_path('app/public/insurance_case_docs/results/' . $isn . '_insurance_payment.xlsx');
//        dd($path);
                    $attachment->insurance_case_id = session('caseId');
                    $attachment->filename = $path;
                    $attachment->type = 'insurancePayment';
                    $attachment->save();

//        dd(File::get($path));


                    $file = File::get($path);

                    $base64String = base64_encode($file);
                    //Making a Plea isn for uploading attachments
//                dd($value);
                    $results[] = $kias->saveAttachment(
                        $plea,
                        basename($path),
                        $base64String,
                        'D'
                    );

            }



            return response()->json([
                'result' => isset($results->ISN) ? (string)$results->ISN : '',
                'results' => $results,
                'base64String' => $base64String,
                'success' => $request->data,
                'full-data' => $full_data,
            ]);


    }
    public function setQrPo(Request $request, KiasServiceInterface $kias)
    {
        //        dd($request->paths);
//        var_dump(count($request->paths));
//        dd($request->info);
//        dd($request);
//        dd($request->info[0]['plea']);
//        $pathh = $request->path;
//        $trimmed = trim($pathh);
        //dd($path);
        $info_client = [];
//        dd($request);
//        $cnt = count($request->paths);
//        dd($cnt);
        $plea = '';
        $isn = '';

//            var_dump($key);
//            dd($cnt);
//            dd($request->info);
        foreach ($request->info as $key => $value) {
//            dd($value);
            $plea = $value['plea'];
            $full_data = $value['full_data'];
//            dd($full_data);
            $isn = $value['isn'];
            $refund = TblForPayEds::find($value['id']);
            $full_data = $refund->full_data;
            //dd($full_data);
            if ($value['confirmed'] == 1) {
                $refund->confirmed = 1;
                $refund->signed = 1;
            }
            $kek = 321;
//          $destinationPath=storage_path('app/public/insurance_case_docs/results/123.xlsx');
//          $success = \File::copy($pathh,$destinationPath);
            $repeat = count($request->paths);
//          dd($value['plea']);
//          dd($request);
            foreach ($request->paths as  $allpath) {
                $file = $allpath;


//        $destination = storage_path('app/public/insurance_case_docs/results/' . $kek .'_insurance_payment.xlsx');
//        Storage::copy($file,$destination);

                $homepage = file_get_contents(strval($file));
            }

//        dd($homepage);
//        $iin = Auth::user()->iin;
//        dd($iin);
//        $spreadsheet = $path;
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
//          dd($kek);

//        $spreadsheet= storage_path($request->path);
//        Изменения
            $sheet = $spreadsheet->getActiveSheet();
            //       QR тут костыльным методом ложится

            $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
//        $qr=QrCode::format('png')->size(300)->generate($info_client);
//        dd($kek);
            $qr=QrCode::format('png')->size(300)->generate($full_data);
//                    dd($qr);
            Storage::disk('local')->put($isn.'_payment_request.png', $qr);
//                    $drawing->setImageResource(imagecreatefrompng($qr));
            $drawing->setPath(storage_path('app/'.$isn.'_payment_request.png')); // put your path and image here
            $drawing->setOffsetX(110);
            $drawing->setRotation(0);
            $drawing->setCoordinates('AE48');
            $drawing->getShadow()->setVisible(true);
            $drawing->getShadow()->setDirection(45);
            $drawing->setWorksheet($spreadsheet->getActiveSheet());
            $writer = new Xlsx($spreadsheet);
//
            $writer->save(storage_path('app/public/insurance_case_docs/results/' . $isn . '_zayvlenie_na_ocenky.xlsx'));
//        dd('kek');
            //       QR тут костыльным методом ложится

            //Вставка в файл
//            $writer = new Xlsx($spreadsheet);
            $attachment = new Attachment();
            $writer->save(storage_path('app/public/insurance_case_docs/results/' . $isn . '_zayvlenie_na_ocenky.xlsx'));
            $path = storage_path('app/public/insurance_case_docs/results/' . $isn . '_zayvlenie_na_ocenky.xlsx');
//        dd($path);
            $attachment->insurance_case_id = session('caseId');
            $attachment->filename = $path;
            $attachment->type = 'insurancePayment';
            $attachment->save();

//        dd(File::get($path));


            $file = File::get($path);

            $base64String = base64_encode($file);
            //Making a Plea isn for uploading attachments
//                dd($value);
            $results[] = $kias->saveAttachment(
                $plea,
                basename($path),
                $base64String,
                'D'
            );

        }



        return response()->json([
            'result' => isset($results->ISN) ? (string)$results->ISN : '',
            'results' => $results,
            'base64String' => $base64String,
            'success' => $request->data,
            'full-data' => $full_data,
        ]);



    }

    public function saveDocument(Request $request, KiasServiceInterface $kias) {
        $success = false;
        $info = $request->data;
        if($info['confirmed'] == 1){

            $rv[] = array(
                'isn' => 0,
                'delete' => 0,
                "valn1" => $info['rv_isn'],
                //"valc$i" => '',
                //"vald$i" => date('d.m.Y'),
            );

            $createLS = $kias->saveDocument($request->classISN,$request->emplISN,'',$rv,[]);
            if(isset($createLS->error)){
                $success = false;
                $error = (string)$createLS->error->text;
            } else {
                if(isset($createLS->DocISN)){
                    $buttonClick = $kias->buttonClick(intval($createLS->DocISN),'BUTTON1');
                    if(isset($buttonClick->error)){
                        $success = false;
                        $error = (string)$buttonClick->error->text;
                    } else {
                        $refund = Refund::find($info['id']);
                        if ($info['confirmed'] == 1) {
                            $refund->confirmed = 1;
                            $refund->main_doc_isn = $createLS->DocISN;
                        } else {
                            $refund->iin_fail = 1;
                        }
                        if ($refund->save()) {
                            $success = true;
                        }
                    }
                }
            }
        }

       return response()->json([
           'success' => isset($success) ? $success : true,
           'error' => isset($error) ? $error : ''
       ]);
    }
    public function saveDocumentPR(Request $request, KiasServiceInterface $kias) {
//        dd($request);
//        $ISN = isset($request->isn) ? $request->isn : '';
//        $ISN = isset($request->refISN) ? $request->refISN : '';
//        $ISN=3988127;
//        $type = isset($request->type) ? $request->type : '';
//        $format = isset($request->edsType) ? $request->edsType : '';
//        $refISN = isset($request->refISN) ? $request->refISN : '';
//        $refID = isset($request->refID ) ? $request->refID  : '';
//        $docClass = isset($request->docClass ) ? $request->docClass  : '';
//        dd($request->data);
        //$test = $kias->getDocRowAttr(1920701,'');
        //dd($test);
        $rv = [];
        if($request->data){
            $i = 1;
            foreach($request->data as $info){
                if($info['confirmed'] == 1){
                    $xslfilepaths=$kias->getAttachmentPath("D",'21-001982/1',"cms",'',551783,$info['product_family_isn']);
//                    $sigFiles = $kias->getAttachmentPath($type,$refID,$format,$docClass,$refISN,$ISN);
                    if(isset($xslfilepaths->error)){
                        return response()->json([
                            'success' => false,
                            'result' => (string)$xslfilepaths->error->text
                        ]);
                    } else {
                        foreach ($xslfilepaths->ROWSET->row as $file) {
                            $files[] = ['filepath' => (string)$file->FILEPATH, 'docISN' => (string)$file->ISN];
                        }
                    }
                    $rv[] = array(
                        "valn$i" => $info['isn'],
                        //"valc$i" => '',
                        //"vald$i" => date('d.m.Y'),
                    );
                    $i++;
                }
            }
        }
        if(count($rv) > 0){
            $save = $kias->saveDocument($request->classISN,"551783","21-001982/1",$request->emplISN,'',$rv,[]);
            if(isset($save->error)){
                $success = false;
                $error = (string)$save->error->text;
            } else {
                if(isset($save->DocISN)){
//                    $buttonClick = $kias->buttonClick(intval($save->DocISN),'BUTTON1');
//                    if(isset($buttonClick->error)){
//                        $success = false;
//                        $error = ( string)$buttonClick->error->text;
//                    } else {

                        foreach ($request->data as $info) {
                            $refund = TblForPayRequest::find($info['id']);
                            if ($info['confirmed'] == 1) {
                                $refund->full_data = $full_data = json_encode($info);
                                $refund->confirmed = 1;
//                                dd($xslfilepaths);
                            } else {
                                $refund->iin_fail = 1;
                            }
                            if ($refund->save()) {
                                $success = true;
                            }
                        }
//                        dd($request->data);\
//                    $lol = TblForPayRequest::where('isn',3988127)->select('id','isn','confirmed','iin','iin_fail')->get()->toArray();
//                    dd($lol);

//                    $od = Refund::where('confirmed',0)->select('rv_isn','id','confirmed','iin','iin_fail')->get();
//                    foreach ($request->data as $info) {
////                        $lol = TblForPayRequest::where('isn',$info['product_family_isn'])->select('id','isn','confirmed','iin','pathtoxsl')->toArray()->get();
////                        dd($lol);
////                        dd($lol[0]['pathtoxsl']);
////                        $id = ($lol[0]['id']);
////                        $refund = TblForPayRequest::find($info['id']);
////                        $mainxsl = TblForPayRequest::find('product_family_isn');
//
//                        $refund = TblForPayRequest::find($id);
////                        var_dump($info['product_family_isn']);
////                        dd($refund);
////                        dd($info);
////                        dd($info);
//
//                        if ($info['confirmed'] == 1) {
//                            $refund->confirmed = 1;
////                            dd($files);
////                            dd($lol[0]['pathtoxsl']);
//                            $lol[0]['pathtoxsl'] = $xslfilepaths->ROWSET->row->FILEPATH;
//
////                                dd($xslfilepaths);
//                        } else {
//                            $refund->iin_fail = 1;
//                        }
//                        if ($refund->save()) {
//                            $success = true;
//                        }
//                    }

                    }
                }
            }

        return response()->json([
            'path'=>isset($xslfilepaths) ? $xslfilepaths : '',
            'success' => isset($success) ? $success : true,
            'error' => isset($error) ? $error : '',
            'result' => $files
        ]);
    }
    public function saveDocumentPO(Request $request, KiasServiceInterface $kias)
    {
        $rv = [];
        if ($request->data) {
            $i = 1;
            foreach ($request->data as $info) {
                if ($info['confirmed'] == 1) {
                    $xslfilepaths = $kias->getAttachmentPath("D", '21-001982/1', "cms", '', 551783, $info['product_family_isn']);
//                    $sigFiles = $kias->getAttachmentPath($type,$refID,$format,$docClass,$refISN,$ISN);
                    if (isset($xslfilepaths->error)) {
                        return response()->json([
                            'success' => false,
                            'result' => (string)$xslfilepaths->error->text
                        ]);
                    } else {
                        foreach ($xslfilepaths->ROWSET->row as $file) {
                            $files[] = ['filepath' => (string)$file->FILEPATH, 'docISN' => (string)$file->ISN];
                        }
                    }
                    $rv[] = array(
                        "valn$i" => $info['isn'],
                    );
                    $i++;
                }
            }
        }
        if (count($rv) > 0) {
            $save = $kias->saveDocument($request->classISN, "551783", "21-001982/1", $request->emplISN, '', $rv, []);
            if (isset($save->error)) {
                $success = false;
                $error = (string)$save->error->text;
            } else {
                if (isset($save->DocISN)) {
                    foreach ($request->data as $info) {
                        $refund = TblForPayEds::find($info['id']);
                        if ($info['confirmed'] == 1) {
                            $refund->full_data = $full_data = json_encode($info);
                            $refund->confirmed = 1;
                        } else {
                            $refund->iin_fail = 1;
                        }
                        if ($refund->save()) {
                            $success = true;
                        }

                    }
                }
            }
        }

        return response()->json([
            'path'=>isset($xslfilepaths) ? $xslfilepaths : '',
            'success' => isset($success) ? $success : true,
            'error' => isset($error) ? $error : '',
            'result' => $files
        ]);
    }

    public function getOrSetDoc(Request $request, KiasServiceInterface $kias){
        $success = false;
        $info = $request->data;
        if($info['confirmed'] == 1){
            $setStatus = $kias->getOrSetDocs($info['rv_isn'],1,2522);    // 2522 - статус на подписании
            if(isset($setStatus->error)){
                $success = false;
                $error = (string)$setStatus->error->text;
            } else {
                if(isset($setStatus->Status)){
                    $refund = Refund::find($info['id']);
                    if ($info['confirmed'] == 1) {
                        $refund->confirmed = 1;
                        $refund->main_doc_isn = $setStatus->Status;
                        $response = $kias->getSubject(null, null, null, $refund->iin);
                        $data = ['email' => isset($response->ROWSET->row[0]->EMAIL) ? (string)$response->ROWSET->row[0]->EMAIL : '', 'status' => 0, 'refund' => $refund];
                        $this->sendEmailService->sendMailRefundStatus($data);
                    } else {
                        $refund->iin_fail = 1;
                    }
                    if ($refund->save()) {
                        $success = true;
                    }
                }
            }
        }
        return response()->json([
            'success' => isset($success) ? $success : true,
            'error' => isset($error) ? $error : ''
        ]);
    }

    public function saveFailStatus(Request $request){
        foreach($request->data as $info){
            $refund = Refund::find($info['id']);
            $refund->iin_fail = 1;
            try{
                if($refund->save()){
                    $success = true;
                }
            }catch (\Mockery\Exception $e){
                $success = false;
                $error = 'Возникла ошибка при сохранении статуса';
            }

        }
        return response()->json([
            'success' => isset($success) ? $success : true,
            'error' => isset($error) ? $error : ''
        ]);
    }
    public function saveFailStatusPR(Request $request){
        foreach($request->data as $info){
            $tblforpayrequest = TblForPayRequest::find($info['id']);
            $tblforpayrequest->iin_fail = 1;
            try{
                if($tblforpayrequest->save()){
                    $success = true;
                }
            }catch (\Mockery\Exception $e){
                $success = false;
                $error = 'Возникла ошибка при сохранении статуса';
            }

        }
        return response()->json([
            'success' => isset($success) ? $success : true,
            'error' => isset($error) ? $error : ''
        ]);
    }
    public function saveFailStatusPO(Request $request){
        foreach($request->data as $info){
            $refund = TblForPayEds::find($info['id']);
            $refund->iin_fail = 1;
            try{
                if($refund->save()){
                    $success = true;
                }
            }catch (\Mockery\Exception $e){
                $success = false;
                $error = 'Возникла ошибка при сохранении статуса';
            }

        }
        return response()->json([
            'success' => isset($success) ? $success : true,
            'error' => isset($error) ? $error : ''
        ]);
    }

    public function testEds(){
        return view('eds');
    }

    public function getEdsTokenForSign(){
        $success = false;
        $curl = curl_init();
        curl_setopt_array($curl,array(
            CURLOPT_URL => "http://ncalayer.uchet.kz:8080/getSignToken",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_POSTFIELDS => "{\n\t\"company_token\":\"7006cebf-82b9-4dbf-9cca-7d35d2eaf763\"\n}",
            CURLOPT_HTTPHEADER => array("Content-Type: application/json"),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        $response = json_decode($response);

        //$response = (object)['token' => 'c4906463-8544-11eb-bb63-000c296105aa'];
        //$response = json_decode((string)$response);
        if(isset($response->token)){
            $success = true;
        }
        return response()->json([
            'success' => $success,
            'result' => $response
        ]);
//        $client = new Client();
//        $res = $client->get('http://ncalayer.uchet.kz:8080/getSignToken', ['json' => ['company_token'=>'7006cebf-82b9-4dbf-9cca-7d35d2eaf763']]);
//        echo $res->getBody();
//        echo $res->getStatusCode();
    }

    public function edsByIsn(Request $request,KiasServiceInterface $kias){
        $files = [];
        $ISN = isset($request->isn) ? $request->isn : '';
        $type = isset($request->type) ? $request->type : 'A';
        $format = isset($request->edsType) ? $request->edsType : '';
        $refISN = isset($request->refISN) ? $request->refISN : '';
        $refID = isset($request->refID ) ? $request->refID  : '';
        $docClass = isset($request->docClass ) ? $request->docClass  : '';

        $sigFiles = $kias->getAttachmentPath($type,$refID,$format,$docClass,$refISN,$ISN);
        if(isset($sigFiles->error)){
            return response()->json([
                'success' => false,
                'result' => (string)$sigFiles->error->text
            ]);
        } else {
            foreach ($sigFiles->ROWSET->row as $file) {
                //$signedBase64 = base64_encode('192.168.1.36\FILESKIAS$\D\33\877\D33877881\3860996.sig');
                if($refISN != '') {
                    $attachment = $kias->getAttachmentData($refISN, intval($file->ISN), $type);
                    if (isset($attachment->FILEDATA, $attachment->FILENAME)) {
                        $signedBase64 = (string)$attachment->FILEDATA;
                    }
                }

                array_push($files, [
                    'filepath' => (string)$file->FILEPATH,
                    'docISN' => (string)$file->ISN,
                    'signedBase64' => isset($signedBase64) ? $signedBase64 : ''
                ]);
            }
        }
        $data = (new User)->getUserData($kias);
        return response()->json([
            'success' => true,
            'result' => $files,
            'iin'=>$data
        ]);
    }

    public function getPrintableOrderDocument(Request $request, KiasServiceInterface $kias){
        $dataParams = [];
        $printableList = (array)$kias->getPrintableDocumentList($request->isn, 1)->ROWSET->row;
        if(isset($printableList['params']->row)){
            foreach($printableList['params']->row as $item){
                $dataParams[] = $item;
            }
        }
        $printable = isset($request->isn) ? $kias->getPrintableOrderDocument($printableList, $dataParams) : null;
        if (isset($printable->Bytes, $printable->FileName)) {
            $base64Document = str_replace("\n", '', (string)$printable->Bytes);
            $success = true;
        }
        return response()->json([
            'success' => isset($base64Document) ? true : false,
            'result' => isset($base64Document) ? $base64Document : null
        ]);
    }

    public function saveEdsInfo(Request $request,KiasServiceInterface $kias){
        $data = $request->data;
        $response = $kias->cicSaveEDS($request->refIsn,$request->isn,$data['iin'],$data['name'],'',$data['tspDate'],$data['certificateValidityPeriod'],'');

        if(isset($response->error)){
            return response()->json([
                'success' => false,
                'result' => (string)$response->error->text
            ]);
        }
        //if(isset($response->result)) {
        return response()->json([
            'success' => true
        ]);
        //}
    }
}
