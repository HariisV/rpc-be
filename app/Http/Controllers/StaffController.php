<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\Staff\exportStaff;
use Illuminate\Http\Request;
use App\Mail\SendEmail;
use GuzzleHttp\Client;
use Carbon\Carbon;
use Validator;
use File;
use DB;

class StaffController extends Controller
{

    private $client;
    private $api_key;
    private $country;

    public function insertStaff(Request $request)
    {

        if (adminAccess($request->user()->id) != 1) {

            return response()->json([
                'message' => 'The user role was invalid.',
                'errors' => 'User Access not Authorize!',
            ], 403);
        }
        DB::beginTransaction();

        try {

            $validate = Validator::make(
                $request->all(),
                [
                    'firstName' => 'required|max:20|min:3',
                    'middleName' => 'max:20|min:3|nullable',
                    'lastName' => 'max:20|min:3|nullable',
                    'nickName' => 'max:20|min:3|nullable',
                    'gender' => 'string|nullable',
                    'status' => 'required|integer',
                    'jobTitleId' => 'required|integer',
                    'startDate' => 'required|date',
                    'endDate' => 'required|date|after:startDate',
                    'registrationNo' => 'string|max:20|min:5|nullable',
                    'designation' => 'string|max:20|min:5|nullable',
                    'locationId' => 'required|integer',
                    'annualSickAllowance' => 'integer|nullable',
                    'annualLeaveAllowance' => 'integer|nullable',
                    'payPeriodId' => 'required|integer',
                    'payAmount' => 'numeric|nullable',
                    'typeId' => 'required|integer',
                    'identificationNumber' => 'string|nullable|max:30',
                    'additionalInfo' => 'string|nullable|max:100',
                    'generalCustomerCanSchedule' => 'integer|nullable',
                    'generalCustomerReceiveDailyEmail' => 'integer|nullable',
                    'generalAllowMemberToLogUsingEmail' => 'integer|nullable',
                    'reminderEmail' => 'integer|nullable',
                    'reminderWhatsapp' => 'integer|nullable',
                    'roleId' => 'required|integer',
                ]
            );



            if ($request->typeId == 3) {

                if ((is_numeric($request->identificationNumber))) {

                    return response()->json([
                        'message' => 'The given data was invalid.',
                        'errors' => "Identification number must be alpanumeric if identification type is passport!",
                    ], 422);
                }
            } else {

                if (!is_numeric($request->identificationNumber) && is_int((int)$request->identificationNumber)) {

                    return response()->json([
                        'message' => 'The given data was invalid.',
                        'errors' => "Identification number must be integer!",
                    ], 422);
                }
            }



            $start = Carbon::parse($request->startDate);
            $end = Carbon::parse($request->endDate);

            if ($validate->fails()) {
                $errors = $validate->errors()->all();

                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => $errors,
                ], 422);
            }


            $data_item = [];

            if ($request->detailAddress) {

                $arrayDetailAddress = json_decode($request->detailAddress, true);

                $messageAddress = [
                    'addressName.required' => 'Address name on tab Address is required',
                    'provinceCode.required' => 'Province code on tab Address is required',
                    'cityCode.required' => 'City code on tab Address is required',
                    'country.required' => 'Country on tab Address is required',
                ];


                $primaryCount = 0;
                foreach ($arrayDetailAddress as $item) {
                    if (isset($item['isPrimary']) && $item['isPrimary'] == 1) {
                        $primaryCount++;
                    }
                }

                if ($primaryCount == 0) {
                    return response()->json([
                        'message' => 'Inputed data is not valid',
                        'errors' => 'Detail address must have at least 1 primary address',
                    ], 422);
                } elseif ($primaryCount > 1) {
                    return response()->json([
                        'message' => 'Inputed data is not valid',
                        'errors' => 'Detail address have 2 primary address, please check again',
                    ], 422);
                }

                foreach ($arrayDetailAddress as $key) {

                    $validateDetail = Validator::make(
                        $key,
                        [
                            'addressName' => 'required',
                            'provinceCode' => 'required',
                            'cityCode' => 'required',
                            'country' => 'required',
                        ],
                        $messageAddress
                    );

                    if ($validateDetail->fails()) {

                        $errors = $validateDetail->errors()->all();

                        foreach ($errors as $checkisu) {

                            if (!(in_array($checkisu, $data_item))) {
                                array_push($data_item, $checkisu);
                            }
                        }
                    }
                }

                if ($data_item) {

                    return response()->json([
                        'message' =>  'Inputed data is not valid',
                        'errors' => $data_item,
                    ], 422);
                }
            } else {

                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => ['Detail address can not be empty!'],
                ], 422);
            }




            $data_telephone = [];

            if ($request->telephone) {

                $arraytelephone = json_decode($request->telephone, true);

                $messagePhone = [
                    'phoneNumber.required' => 'Phone Number on tab telephone is required',
                    'type.required' => 'Type on tab telephone is required',
                    'usage.required' => 'Usage on tab telephone is required',
                ];

                foreach ($arraytelephone as $key) {

                    $validateTelephone = Validator::make(
                        $key,
                        [
                            'phoneNumber' => 'required',
                            'type' => 'required',
                            'usage' => 'required',
                        ],
                        $messagePhone
                    );

                    if ($validateTelephone->fails()) {

                        $errors = $validateTelephone->errors()->all();

                        foreach ($errors as $checkisu) {

                            if (!(in_array($checkisu, $data_telephone))) {
                                array_push($data_telephone, $checkisu);
                            }
                        }
                    }

                    if (strtolower($key['type']) == "whatshapp") {

                        if (!(substr($key['phoneNumber'], 0, 2) === "62")) {
                            return response()->json([
                                'message' => 'Inputed data is not valid',
                                'errors' => 'Please check your phone number, for type whatshapp must start with 62',
                            ], 422);
                        }
                    }
                }

                if ($data_telephone) {
                    return response()->json([
                        'message' => 'Inputed data is not valid',
                        'errors' => $data_telephone,
                    ], 422);
                }

                $checkTelephone = [];

                foreach ($arraytelephone as $val) {

                    $checkIfTelephoneAlreadyExists = DB::table('usersTelephones')
                        ->where([
                            ['phoneNumber', '=', $val['phoneNumber'],],
                            ['isDeleted', '=', '0']
                        ])
                        ->first();

                    if ($checkIfTelephoneAlreadyExists) {
                        array_push($checkTelephone, 'Phonenumber : ' . $val['phoneNumber'] . ' already exists, please try different number');
                    }
                }


                if ($checkTelephone) {
                    return response()->json([
                        'message' => 'Inputed data is not valid',
                        'errors' => $checkTelephone,
                    ], 422);
                }
            }

            $data_error_email = [];
            $insertEmailUsers = '';
            if ($request->email) {

                $arrayemail = json_decode($request->email, true);

                $messageEmail = [
                    'email.required' => 'Email on tab email is required',
                    'usage.required' => 'Usage on tab email is required',
                ];

                foreach ($arrayemail as $key) {

                    $validateEmail = Validator::make(
                        $key,
                        [
                            'email' => 'required',
                            'usage' => 'required',
                        ],
                        $messageEmail
                    );

                    if ($validateEmail->fails()) {

                        $errors = $validateEmail->errors()->all();

                        foreach ($errors as $checkisu) {

                            if (!(in_array($checkisu, $data_error_email))) {
                                array_push($data_error_email, $checkisu);
                            }
                        }
                    }
                }


                if ($data_error_email) {
                    return response()->json([
                        'message' => 'The given data was invalid.',
                        'errors' =>  $data_error_email,
                    ], 422);
                }
                
                $checkUsageEmail = false;
                $checkEmail = [];
                foreach ($arrayemail as $val) {

                    $checkIfEmailExists = DB::table('usersEmails')
                        ->where([
                            ['email', '=', $val['email'],],
                            ['isDeleted', '=', '0']
                        ])
                        ->first();

                    if ($checkIfEmailExists) {
                        array_push($checkEmail, 'Email : ' . $val['email'] . ' already exists, please try different email address');
                    }

                    if ($val['usage'] == 'Utama') {
                        $checkUsageEmail = true;
                        $insertEmailUsers = $val['email'];
                    }
                }

                if ($checkEmail) {
                    return response()->json([
                        'message' => 'Inputed data is not valid',
                        'errors' => $checkEmail,
                    ], 422);
                }
                
                if ($checkUsageEmail == false) {
                    return response()->json([
                        'message' => 'Inputed data is not valid',
                        'errors' => 'Must have one primary email',
                    ], 422);
                }
            } else {

                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => ['Email can not be empty!'],
                ], 422);
            }


            $data_error_messenger = [];

            if ($request->messenger) {

                $arraymessenger = json_decode($request->messenger, true);

                $messageMessenger = [
                    'messengerNumber.required' => 'messenger number on tab messenger is required',
                    'type.required' => 'Type on tab messenger is required',
                    'usage.required' => 'Usage on tab messenger is required',
                ];

                foreach ($arraymessenger as $key) {

                    $validateMessenger = Validator::make(
                        $key,
                        [
                            'messengerNumber' => 'required',
                            'type' => 'required',
                            'usage' => 'required',
                        ],
                        $messageMessenger
                    );

                    if ($validateMessenger->fails()) {

                        $errors = $validateMessenger->errors()->all();

                        foreach ($errors as $checkisu) {

                            if (!(in_array($checkisu, $data_error_messenger))) {
                                array_push($data_error_messenger, $checkisu);
                            }
                        }
                    }


                    if (strtolower($key['type']) == "whatshapp") {

                        if (!(substr($key['messengerNumber'], 0, 3) === "62")) {

                            return response()->json([
                                'message' => 'Inputed data is not valid',
                                'errors' => 'Please check your phone number, for type whatshapp must start with 62',
                            ], 422);
                        }
                    }
                }
                

                if ($data_error_messenger) {
                    return response()->json([
                        'message' => 'The given data was invalid.',
                        'errors' => $data_error_messenger,
                    ], 422);
                }

                $checkMessenger = [];
                foreach ($arraymessenger as $val) {

                    $checkifMessengerExists = DB::table('usersMessengers')
                        ->where([
                            ['messengerNumber', '=', $val['messengerNumber'],],
                            ['isDeleted', '=', '0']
                        ])
                        ->first();

                    if ($checkifMessengerExists) {
                        array_push($checkMessenger, 'Messenger number  : ' . $val['messengerNumber'] . ' already exists, please try different number');
                    }
                }

                if ($checkMessenger) {
                    return response()->json([
                        'message' => 'Inputed data is not valid',
                        'errors' => $checkMessenger,
                    ], 422);
                }
            }
            

            $lastInsertedID = DB::table('users')
                ->insertGetId([
                    'firstName' => $request->firstName,
                    'middleName' => $request->middleName,
                    'lastName' => $request->lastName,
                    'nickName' => $request->nickName,
                    'gender' => $request->gender,
                    'status' => $request->status,
                    'jobTitleId' => $request->jobTitleId,
                    'startDate' =>  $start,
                    'endDate' => $end,
                    'registrationNo' => $request->registrationNo,
                    'designation' => $request->designation,
                    'locationId' => $request->locationId,
                    'annualSickAllowance' => $request->annualSickAllowance,
                    'annualSickAllowanceRemaining' => $request->annualSickAllowance,
                    'annualLeaveAllowance' => $request->annualLeaveAllowance,
                    'annualLeaveAllowanceRemaining' => $request->annualLeaveAllowance,
                    'payPeriodId' => $request->payPeriodId,
                    'payAmount' => $request->payAmount,
                    'typeId' => $request->typeId,
                    'identificationNumber' => $request->identificationNumber,
                    'additionalInfo' => $request->additionalInfo,
                    'generalCustomerCanSchedule' => $request->generalCustomerCanSchedule,
                    'generalCustomerReceiveDailyEmail' => $request->generalCustomerReceiveDailyEmail,
                    'generalAllowMemberToLogUsingEmail' => $request->generalAllowMemberToLogUsingEmail,
                    'reminderEmail' => $request->reminderEmail,
                    'reminderWhatsapp' => $request->reminderWhatsapp,
                    'roleId' => $request->roleId,
                    'isDeleted' => 0,
                    'createdBy' => $request->user()->firstName,
                    'email' => $insertEmailUsers,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'password' => null,
                ]);


            if ($request->detailAddress) {

                foreach ($arrayDetailAddress as $val) {

                    DB::table('usersDetailAddresses')
                        ->insert([
                            'usersId' => $lastInsertedID,
                            'addressName' => $val['addressName'],
                            'additionalInfo' => $val['additionalInfo'],
                            'provinceCode' => $val['provinceCode'],
                            'cityCode' => $val['cityCode'],
                            'postalCode' => $val['postalCode'],
                            'country' => $val['country'],
                            'isPrimary' => $val['isPrimary'],
                            'isDeleted' => 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                }
            }


            if ($request->hasfile('image')) {

                $files = $request->file('image');

                $name = $files->hashName();
                $files->move(public_path() . '/UsersImages/', $name);

                $fileName = "/UsersImages/" . $name;

                DB::table('usersImages')
                    ->insert([
                        'usersId' => $lastInsertedID,
                        'imagePath' => $fileName,
                        'isDeleted' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
            }

            if ($request->messenger) {

                foreach ($arraymessenger as $val) {

                    DB::table('usersMessengers')
                        ->insert([
                            'usersId' => $lastInsertedID,
                            'messengerNumber' => $val['messengerNumber'],
                            'type' => $val['type'],
                            'usage' => $val['usage'],
                            'isDeleted' => 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                }
            }

            if ($request->email) {

                foreach ($arrayemail as $val) {

                    DB::table('usersEmails')
                        ->insert([
                            'usersId' => $lastInsertedID,
                            'email' => $val['email'],
                            'usage' => $val['usage'],
                            'isDeleted' => 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                }
            }

            if ($request->telephone) {

                foreach ($arraytelephone as $val) {

                    DB::table('usersTelephones')
                        ->insert([
                            'usersId' => $lastInsertedID,
                            'phoneNumber' => $val['phoneNumber'],
                            'type' => $val['type'],
                            'usage' => $val['usage'],
                            'isDeleted' => 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                }
            }

            if ($request->status == 1) {

                $sendEmailPrimary = DB::table('usersEmails as usersEmails')
                    ->leftjoin('users as users', 'users.id', '=', 'usersEmails.usersId')
                    ->select(
                        'usersEmails.usersId',
                        'usersEmails.email',
                        DB::raw("CONCAT(IFNULL(users.firstName,'') ,' ', IFNULL(users.middleName,'') ,' ', IFNULL(users.lastName,'') ,'(', IFNULL(users.nickName,'') ,')'  ) as name"),
                    )
                    ->where([
                        ['usersEmails.usersId', '=', $lastInsertedID],
                        ['usersEmails.isDeleted', '=', '0'],
                        ['usersEmails.usage', '=', 'Utama']
                    ])
                    ->first();

                $jobtitleName = DB::table('jobTitle')
                    ->select('jobName')
                    ->where([
                        ['id', '=', $request->jobTitleId],
                        ['isActive', '=', 1]
                    ])
                    ->first();

                $data = [
                    'subject' => 'RPC Petshop',
                    'body' => 'Please verify your account',
                    'isi' => 'This e-mail was sent from a notification-only address that cannot accept incoming e-mails. Please do not reply to this message.',
                    'name' => $sendEmailPrimary->name,
                    'email' => $sendEmailPrimary->email,
                    'jobTitle' => $jobtitleName->jobName,
                    'usersId' => $sendEmailPrimary->usersId,
                ];

                Mail::to($sendEmailPrimary->email)->send(new SendEmail($data));

                DB::commit();

                return response()->json(
                    [
                        'result' => 'success',
                        'message' => 'Insert Data Users Successful! Please check your email for verification',
                    ],
                    200
                );
            } else {

                DB::commit();

                return response()->json(
                    [
                        'result' => 'success',
                        'message' => 'Insert Data Users Successful!',
                    ],
                    200
                );
            }


            DB::commit();

            return response()->json(
                [
                    'result' => 'success',
                    'message' => 'Insert Data Users Successful!',
                ],
                200
            );
        } catch (Exception $e) {

            DB::rollback();

            return response()->json([
                'result' => 'failed',
                'message' => $e,
            ], 422);
        }
    }

    public function sendEmailVerification(Request $request)
    {

        if (adminAccess($request->user()->id) != 1) {
            return response()->json([
                'message' => 'The user role was invalid.',
                'errors' => ['User Access not Authorize!'],
            ], 403);
        }

        $validate = Validator::make($request->all(), [
            'id' => 'required',
        ]);

        if ($validate->fails()) {
            $errors = $validate->errors()->all();

            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $errors,
            ], 422);
        }

        DB::beginTransaction();
        try {

            $checkIfDataExits = DB::table('users')
                ->where([
                    ['id', '=', $request->id],
                    ['isDeleted', '=', 0],
                ])
                ->first();

            if (!$checkIfDataExits) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => ['Data not found! try different ID'],
                ], 422);
            }


            $users = DB::table('users')
                ->select(
                    'status',
                    'password',
                )
                ->where('id', '=', $request->id)
                ->first();

            if ($users->status == 0) {
                return response()->json([
                    'message' => 'Failed',
                    'errors' => 'Please activated your account first',
                ], 406);
            } else {

                
                if ($users->password != null) {
                    return response()->json([
                        'message' => 'failed',
                        'errors' => 'Your account password has been set and verified within email',
                    ], 406);
                } else {

                    $sendEmailPrimary = DB::table('users')
                        ->select(
                            'jobTitleId',
                            'email',
                            DB::raw("CONCAT(IFNULL(users.firstName,'') ,' ', IFNULL(users.middleName,'') ,' ', IFNULL(users.lastName,'') ,'(', IFNULL(users.nickName,'') ,')'  ) as name"),
                        )
                        ->where([
                            ['id', '=', $request->id],
                            ['isDeleted', '=', 0],

                        ])
                        ->first();


                    $jobtitleName = DB::table('jobTitle')
                        ->select('jobName')
                        ->where([
                            ['id', '=', $sendEmailPrimary->jobTitleId],
                            ['isActive', '=', 1]
                        ])
                        ->first();

                    $data = [
                        'subject' => 'RPC Petshop',
                        'body' => 'Please verify your account',
                        'isi' => 'This e-mail was sent from a notification-only address that cannot accept incoming e-mails. Please do not reply to this message.',
                        'name' => $sendEmailPrimary->name,
                        'email' => $sendEmailPrimary->email,
                        'jobTitle' => $jobtitleName->jobName,
                        'usersId' => $request->id,
                    ];

                    Mail::to($sendEmailPrimary->email)->send(new SendEmail($data));
                }
            }

            return response()->json([
                'result' => 'success',
                'message' => 'Successfully send email! Please check your email for verification',
            ], 200);
        } catch (Exception $e) {

            DB::rollback();

            return response()->json([
                'message' => 'failed',
                'errors' => $e,
            ], 422);
        }
    }
    

    public function updateStatusUsers(Request $request)
    {

        if (adminAccess($request->user()->id) != 1) {
            return response()->json([
                'message' => 'The user role was invalid.',
                'errors' => ['User Access not Authorize!'],
            ], 403);
        }

        $validate = Validator::make($request->all(), [
            'id' => 'required',
        ]);

        if ($validate->fails()) {
            $errors = $validate->errors()->all();

            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $errors,
            ], 422);
        }

        DB::beginTransaction();
        try {

            $checkIfDataExits = DB::table('users')
                ->where([
                    ['id', '=', $request->id],
                    ['isDeleted', '=', 0],
                ])
                ->first();

            if (!$checkIfDataExits) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => ['Data not found! try different ID'],
                ], 422);
            }

            $users = DB::table('users')
                ->select('status')
                ->where('id', '=', $request->id)
                ->first();

            if ($users->status == 1) {
                return response()->json([
                    'message' => 'failed',
                    'errors' => 'Your account already been activated',
                ], 406);
            } else {
                
                $users = DB::table('users')
                    ->select('status')
                    ->where('id', '=', $request->id)
                    ->first();

                DB::table('users')
                    ->where('id', '=', $request->id)
                    ->update([
                        'status' => 1,
                    ]);

                DB::commit();

                return response()->json([
                    'result' => 'success',
                    'message' => 'Successfuly activated your account, you can send email for setting your password',
                ], 200);
            }
        } catch (Exception $e) {

            DB::rollback();

            return response()->json([
                'message' => 'failed',
                'errors' => $e,
            ], 422);
        }
    }

    

    public function getAllHolidaysDate(Request $request)
    {

        try {

            $valYear = null;

            if ($request->year) {
                $valYear = $request->year;
            } else {
                $valYear = date('Y');
            }


            $response = $this->client->request('GET', 'holidays', [
                'query' => [
                    'api_key' => $this->api_key,
                    'country' => $this->country,
                    'year' => $valYear,
                ],
            ]);

            $holidays = json_decode($response->getBody())->response->holidays;

            foreach ($holidays as $val) {

                if ($val->type[0] == "National holiday") {

                    if (DB::table('holidays')
                        ->where('type', $val->type[0])
                        ->where('year', $valYear)
                        ->where('date', $val->date->iso)
                        ->exists()
                    ) {

                        DB::table('holidays')
                            ->where('type', $val->type[0])
                            ->where('date', $val->date->iso)
                            ->where('year', $valYear)
                            ->update([
                                'date' => $val->date->iso,
                                'type' => $val->type[0],
                                'description' => $val->name,
                                'year' => $valYear,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                    } else {

                        DB::table('holidays')
                            ->insert([
                                'date' => $val->date->iso,
                                'type' => $val->type[0],
                                'year' => $valYear,
                                'description' => $val->name,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                    }
                }
            }

            DB::commit();

            return response()->json([
                'result' => 'Success',
                'message' => "Successfully input date holidays",
            ], 200);
        } catch (Exception $e) {

            return response()->json([
                'message' => 'Failed',
                'errors' => $e,
            ], 422);
        }
    }

    
    public function __construct()
    {
        $this->client = new Client([
            'base_uri' => 'https://calendarific.com/api/v2/',
        ]);
        $this->api_key = '40a18b1a57c593a8ba3e949ce44420e52b610171';
        $this->country = 'ID';
    }


    public function getRoleStaff(Request $request)
    {

        try {

            $getRole = DB::table('usersRoles')
                ->select(
                    'id',
                    'roleName'
                )
                ->where([['isActive', '=', '1']])
                ->orderBy('id', 'asc')
                ->get();

            return response()->json($getRole, 200);
        } catch (Exception $e) {

            return response()->json([
                'message' => 'Failed',
                'errors' => $e,
            ], 422);
        }
    }

    
    public function getRoleName(Request $request)
    {

        try {

            $getRole = DB::table('usersRoles')
                ->select(
                    'id',
                    'roleName'
                )
                ->where([['isActive', '=', '1']])
                ->orderBy('id', 'asc')
                ->get();

            return response()->json($getRole, 200);
        } catch (Exception $e) {

            return response()->json([
                'message' => 'Failed',
                'errors' => $e,
            ], 422);
        }
    }


    
    public function index(Request $request)
    {

        try {

            $defaultRowPerPage = 5;
            $defaultOrderBy = "asc";


            $subquery = DB::table('users as a')
                ->leftjoin('jobTitle as b', 'b.id', '=', 'a.jobTitleId')
                ->leftjoin('usersEmails as c', 'c.usersId', '=', 'a.id')
                ->leftjoin('usersTelephones as d', 'd.usersId', '=', 'a.id')
                ->leftjoin('location as e', 'e.id', '=', 'a.locationId')
                ->select(
                    'a.id as id',
                    DB::raw("CONCAT(IFNULL(a.firstName,'') ,' ', IFNULL(a.middleName,'') ,' ', IFNULL(a.lastName,'') ,'(', IFNULL(a.nickName,a.firstName) ,')'  ) as name"),
                    'b.jobName as jobTitle',
                    'c.email as emailAddress',
                    DB::raw("CONCAT(d.phoneNumber) as phoneNumber"),
                    DB::raw("CASE WHEN lower(d.type)='whatshapp' then true else false end as isWhatsapp"),
                    DB::raw("CASE WHEN a.status=1 then 'Active' else 'Non Active' end as status"),
                    'e.locationName as location',
                    'a.locationId as locationId',
                    'a.createdBy as createdBy',
                    DB::raw('DATE_FORMAT(a.created_at, "%d-%m-%Y") as createdAt'),
                    'a.updated_at'
                )
                ->where([
                    ['a.isDeleted', '=', '0'],
                    ['b.isActive', '=', '1'],
                    ['c.usage', '=', 'Utama'],
                    ['c.isDeleted', '=', '0'],
                    ['d.usage', '=', 'Utama'],
                    ['e.isDeleted', '=', '0'],
                ]);


            $data = DB::table($subquery, 'a');

            if ($request->locationId) {

                $val = [];
                foreach ($request->locationId as $temp) {
                    $val = $temp;
                }

                if ($val) {
                    $data = $data->whereIn('a.locationid', $request->locationId);
                }
            }



            if ($request->search) {

                $res = $this->Search($request);
                if ($res == "id") {

                    $data = $data->where('id', 'like', '%' . $request->search . '%');
                } else if ($res == "name") {

                    $data = $data->where('name', 'like', '%' . $request->search . '%');
                } else if ($res == "jobTitle") {

                    $data = $data->where('jobTitle', 'like', '%' . $request->search . '%');
                } else if ($res == "emailAddress") {

                    $data = $data->where('emailAddress', 'like', '%' . $request->search . '%');
                } else if ($res == "phoneNumber") {

                    $data = $data->where('phoneNumber', 'like', '%' . $request->search . '%');
                } else if ($res == "isWhatsapp") {

                    $data = $data->where('isWhatsapp', 'like', '%' . $request->search . '%');
                } else if ($res == "status") {

                    $data = $data->where('status', 'like', '%' . $request->search . '%');
                } else if ($res == "location") {

                    $data = $data->where('location', 'like', '%' . $request->search . '%');
                } else if ($res == "createdBy") {

                    $data = $data->where('createdBy', 'like', '%' . $request->search . '%');
                } else if ($res == "createdAt") {

                    $data = $data->where('createdAt', 'like', '%' . $request->search . '%');
                } else {

                    $data = [];
                    return response()->json([
                        'totalPagination' => 0,
                        'data' => $data
                    ], 200);
                }
            }

            if ($request->orderValue) {

                $defaultOrderBy = $request->orderValue;
            }



            $checkOrder = null;
            if ($request->orderColumn && $defaultOrderBy) {

                $listOrder = array(
                    'id',
                    'name',
                    'jobTitle',
                    'emailAddress',
                    'phoneNumber',
                    'isWhatsapp',
                    'status',
                    'location',
                    'createdBy',
                    'createdAt',
                );

                if (!in_array($request->orderColumn, $listOrder)) {

                    return response()->json([
                        'message' => 'failed',
                        'errors' => 'Please try different order column',
                        'orderColumn' => $listOrder,
                    ]);
                }
                

                if (strtolower($defaultOrderBy) != "asc" && strtolower($defaultOrderBy) != "desc") {
                    return response()->json([
                        'message' => 'failed',
                        'errors' => 'order value must Ascending: ASC or Descending: DESC ',
                    ]);
                }

                $checkOrder = true;
            }

            if ($checkOrder) {

                $data = DB::table($data)
                    ->select(
                        'id',
                        'name',
                        'jobTitle',
                        'emailAddress',
                        'phoneNumber',
                        'isWhatsapp',
                        'status',
                        'location',
                        'createdBy',
                        'createdAt',
                    )
                    ->orderBy($request->orderColumn, $defaultOrderBy)
                    ->orderBy('updated_at', 'desc');
            } else {


                $data = DB::table($data)
                    ->select(
                        'id',
                        'name',
                        'jobTitle',
                        'emailAddress',
                        'phoneNumber',
                        'isWhatsapp',
                        'status',
                        'location',
                        'createdBy',
                        'createdAt',
                    )
                    ->orderBy('updated_at', 'desc');
            }


            if ($request->rowPerPage > 0) {
                $defaultRowPerPage = $request->rowPerPage;
            }

            $goToPage = $request->goToPage;

            $offset = ($goToPage - 1) * $defaultRowPerPage;

            $count_data = $data->count();
            $count_result = $count_data - $offset;

            if ($count_result < 0) {
                $data = $data->offset(0)->limit($defaultRowPerPage)->get();
            } else {
                $data = $data->offset($offset)->limit($defaultRowPerPage)->get();
            }

            $total_paging = $count_data / $defaultRowPerPage;

            return response()->json(['totalPagination' => ceil($total_paging), 'data' => $data], 200);
        } catch (Exception $e) {

            return response()->json([
                'message' => 'Failed',
                'errors' => $e,
            ], 422);
        }
    }

    
    private function Search($request)
    {

        $subquery = DB::table('users as a')
            ->leftjoin('jobTitle as b', 'b.id', '=', 'a.jobTitleId')
            ->leftjoin('usersEmails as c', 'c.usersId', '=', 'a.id')
            ->leftjoin('usersTelephones as d', 'd.usersId', '=', 'a.id')
            ->leftjoin('location as e', 'e.id', '=', 'a.locationId')
            ->select(
                'a.id as id',
                DB::raw("CONCAT(IFNULL(a.firstName,'') ,' ', IFNULL(a.middleName,'') ,' ', IFNULL(a.lastName,'') ,'(', IFNULL(a.nickName,a.firstName) ,')'  ) as name"),
                'b.jobName as jobTitle',
                'c.email as emailAddress',
                DB::raw("CONCAT(d.phoneNumber) as phoneNumber"),
                DB::raw("CASE WHEN lower(d.type)='whatshapp' then true else false end as isWhatsapp"),
                DB::raw("CASE WHEN a.status=1 then 'Active' else 'Non Active' end as status"),
                'e.locationName as location',
                'a.createdBy as createdBy',
                DB::raw(
                    'DATE_FORMAT(a.created_at, "%d-%m-%Y") as createdAt',
                    'a.updated_at'
                )
            )
            ->where([
                ['a.isDeleted', '=', '0'],
                ['b.isActive', '=', '1'],
                ['c.usage', '=', 'Utama'],
                ['c.isDeleted', '=', '0'],
                ['d.usage', '=', 'Utama'],
                ['e.isDeleted', '=', '0'],
            ]);

        $data = DB::table($subquery, 'a');


        if ($request->locationId) {

            $val = [];
            foreach ($request->locationId as $temp) {
                $val = $temp;
            }

            if ($val) {
                $data = $data->whereIn('a.locationid', $request->locationId);
            }
        }

        $data = DB::table($data)
            ->select(
                'id',
                'name',
                'jobTitle',
                'emailAddress',
                'phoneNumber',
                'isWhatsapp',
                'status',
                'location',
                'createdBy',
                'createdAt'
            );

        if ($request->search) {
            $data = $data->where('id', 'like', '%' . $request->search . '%');
        }

        $data = $data->get();

        if (count($data)) {
            $temp_column = 'id';
            return $temp_column;
        }

        $subquery = DB::table('users as a')
            ->leftjoin('jobTitle as b', 'b.id', '=', 'a.jobTitleId')
            ->leftjoin('usersEmails as c', 'c.usersId', '=', 'a.id')
            ->leftjoin('usersTelephones as d', 'd.usersId', '=', 'a.id')
            ->leftjoin('location as e', 'e.id', '=', 'a.locationId')
            ->select(
                'a.id as id',
                DB::raw("CONCAT(IFNULL(a.firstName,'') ,' ', IFNULL(a.middleName,'') ,' ', IFNULL(a.lastName,'') ,'(', IFNULL(a.nickName,a.firstName) ,')'  ) as name"),
                'b.jobName as jobTitle',
                'c.email as emailAddress',
                DB::raw("CONCAT(d.phoneNumber) as phoneNumber"),
                DB::raw("CASE WHEN lower(d.type)='whatshapp' then true else false end as isWhatsapp"),
                DB::raw("CASE WHEN a.status=1 then 'Active' else 'Non Active' end as status"),
                'e.locationName as location',
                'a.createdBy as createdBy',
                DB::raw(
                    'DATE_FORMAT(a.created_at, "%d-%m-%Y") as createdAt',
                    'a.updated_at'
                )
            )
            ->where([
                ['a.isDeleted', '=', '0'],
                ['b.isActive', '=', '1'],
                ['c.usage', '=', 'Utama'],
                ['c.isDeleted', '=', '0'],
                ['d.usage', '=', 'Utama'],
                ['e.isDeleted', '=', '0'],
            ]);


        $data = DB::table($subquery, 'a');


        if ($request->locationId) {

            $val = [];
            foreach ($request->locationId as $temp) {
                $val = $temp;
            }

            if ($val) {
                $data = $data->whereIn('a.locationid', $request->locationId);
            }
        }

        $data = DB::table($data)
            ->select(
                'id',
                'name',
                'jobTitle',
                'emailAddress',
                'phoneNumber',
                'isWhatsapp',
                'status',
                'location',
                'createdBy',
                'createdAt'
            );

        if ($request->search) {
            $data = $data->where('name', 'like', '%' . $request->search . '%');
        }

        $data = $data->get();

        if (count($data)) {
            $temp_column = 'name';
            return $temp_column;
        }

        $subquery = DB::table('users as a')
            ->leftjoin('jobTitle as b', 'b.id', '=', 'a.jobTitleId')
            ->leftjoin('usersEmails as c', 'c.usersId', '=', 'a.id')
            ->leftjoin('usersTelephones as d', 'd.usersId', '=', 'a.id')
            ->leftjoin('location as e', 'e.id', '=', 'a.locationId')
            ->select(
                'a.id as id',
                DB::raw("CONCAT(IFNULL(a.firstName,'') ,' ', IFNULL(a.middleName,'') ,' ', IFNULL(a.lastName,'') ,'(', IFNULL(a.nickName,a.firstName) ,')'  ) as name"),
                'b.jobName as jobTitle',
                'c.email as emailAddress',
                DB::raw("CONCAT(d.phoneNumber) as phoneNumber"),
                DB::raw("CASE WHEN lower(d.type)='whatshapp' then true else false end as isWhatsapp"),
                DB::raw("CASE WHEN a.status=1 then 'Active' else 'Non Active' end as status"),
                'e.locationName as location',
                'a.createdBy as createdBy',
                DB::raw(
                    'DATE_FORMAT(a.created_at, "%d-%m-%Y") as createdAt',
                    'a.updated_at'
                ),
            )
            ->where([
                ['a.isDeleted', '=', '0'],
                ['b.isActive', '=', '1'],
                ['c.usage', '=', 'Utama'],
                ['c.isDeleted', '=', '0'],
                ['d.usage', '=', 'Utama'],
                ['e.isDeleted', '=', '0'],
            ]);


        $data = DB::table($subquery, 'a');

        if ($request->locationId) {

            $val = [];
            foreach ($request->locationId as $temp) {
                $val = $temp;
            }

            if ($val) {
                $data = $data->whereIn('a.locationid', $request->locationId);
            }
        }

        $data = DB::table($data)
            ->select(
                'id',
                'name',
                'jobTitle',
                'emailAddress',
                'phoneNumber',
                'isWhatsapp',
                'status',
                'location',
                'createdBy',
                'createdAt'
            );

        if ($request->search) {
            $data = $data->where('jobTitle', 'like', '%' . $request->search . '%');
        }

        $data = $data->get();

        if (count($data)) {
            $temp_column = 'jobTitle';
            return $temp_column;
        }

        $subquery = DB::table('users as a')
            ->leftjoin('jobTitle as b', 'b.id', '=', 'a.jobTitleId')
            ->leftjoin('usersEmails as c', 'c.usersId', '=', 'a.id')
            ->leftjoin('usersTelephones as d', 'd.usersId', '=', 'a.id')
            ->leftjoin('location as e', 'e.id', '=', 'a.locationId')
            ->select(
                'a.id as id',
                DB::raw("CONCAT(IFNULL(a.firstName,'') ,' ', IFNULL(a.middleName,'') ,' ', IFNULL(a.lastName,'') ,'(', IFNULL(a.nickName,a.firstName) ,')'  ) as name"),
                'b.jobName as jobTitle',
                'c.email as emailAddress',
                DB::raw("CONCAT(d.phoneNumber) as phoneNumber"),
                DB::raw("CASE WHEN lower(d.type)='whatshapp' then true else false end as isWhatsapp"),
                DB::raw("CASE WHEN a.status=1 then 'Active' else 'Non Active' end as status"),
                'e.locationName as location',
                'a.createdBy as createdBy',
                DB::raw(
                    'DATE_FORMAT(a.created_at, "%d-%m-%Y") as createdAt',
                    'a.updated_at'
                )
            )
            ->where([
                ['a.isDeleted', '=', '0'],
                ['b.isActive', '=', '1'],
                ['c.usage', '=', 'Utama'],
                ['c.isDeleted', '=', '0'],
                ['d.usage', '=', 'Utama'],
                ['e.isDeleted', '=', '0'],
            ]);

        $data = DB::table($subquery, 'a');


        if ($request->locationId) {

            $val = [];
            foreach ($request->locationId as $temp) {
                $val = $temp;
            }

            if ($val) {
                $data = $data->whereIn('a.locationid', $request->locationId);
            }
        }

        $data = DB::table($data)
            ->select(
                'id',
                'name',
                'jobTitle',
                'emailAddress',
                'phoneNumber',
                'isWhatsapp',
                'status',
                'location',
                'createdBy',
                'createdAt'
            );

        if ($request->search) {
            $data = $data->where('emailAddress', 'like', '%' . $request->search . '%');
        }

        $data = $data->get();

        if (count($data)) {
            $temp_column = 'emailAddress';
            return $temp_column;
        }


        $subquery = DB::table('users as a')
            ->leftjoin('jobTitle as b', 'b.id', '=', 'a.jobTitleId')
            ->leftjoin('usersEmails as c', 'c.usersId', '=', 'a.id')
            ->leftjoin('usersTelephones as d', 'd.usersId', '=', 'a.id')
            ->leftjoin('location as e', 'e.id', '=', 'a.locationId')
            ->select(
                'a.id as id',
                DB::raw("CONCAT(IFNULL(a.firstName,'') ,' ', IFNULL(a.middleName,'') ,' ', IFNULL(a.lastName,'') ,'(', IFNULL(a.nickName,a.firstName) ,')'  ) as name"),
                'b.jobName as jobTitle',
                'c.email as emailAddress',
                DB::raw("CONCAT(d.phoneNumber) as phoneNumber"),
                DB::raw("CASE WHEN lower(d.type)='whatshapp' then true else false end as isWhatsapp"),
                DB::raw("CASE WHEN a.status=1 then 'Active' else 'Non Active' end as status"),
                'e.locationName as location',
                'a.createdBy as createdBy',
                DB::raw(
                    'DATE_FORMAT(a.created_at, "%d-%m-%Y") as createdAt',
                    'a.updated_at'
                )
            )
            ->where([
                ['a.isDeleted', '=', '0'],
                ['b.isActive', '=', '1'],
                ['c.usage', '=', 'Utama'],
                ['c.isDeleted', '=', '0'],
                ['d.usage', '=', 'Utama'],
                ['e.isDeleted', '=', '0'],
            ]);

        $data = DB::table($subquery, 'a');

        if ($request->locationId) {

            $val = [];
            foreach ($request->locationId as $temp) {
                $val = $temp;
            }

            if ($val) {
                $data = $data->whereIn('a.locationid', $request->locationId);
            }
        }

        $data = DB::table($data)
            ->select(
                'id',
                'name',
                'jobTitle',
                'emailAddress',
                'phoneNumber',
                'isWhatsapp',
                'status',
                'location',
                'createdBy',
                'createdAt'
            );

        if ($request->search) {
            $data = $data->where('phoneNumber', 'like', '%' . $request->search . '%');
        }

        $data = $data->get();

        if (count($data)) {
            $temp_column = 'phoneNumber';
            return $temp_column;
        }


        $subquery = DB::table('users as a')
            ->leftjoin('jobTitle as b', 'b.id', '=', 'a.jobTitleId')
            ->leftjoin('usersEmails as c', 'c.usersId', '=', 'a.id')
            ->leftjoin('usersTelephones as d', 'd.usersId', '=', 'a.id')
            ->leftjoin('location as e', 'e.id', '=', 'a.locationId')
            ->select(
                'a.id as id',
                DB::raw("CONCAT(IFNULL(a.firstName,'') ,' ', IFNULL(a.middleName,'') ,' ', IFNULL(a.lastName,'') ,'(', IFNULL(a.nickName,a.firstName) ,')'  ) as name"),
                'b.jobName as jobTitle',
                'c.email as emailAddress',
                DB::raw("CONCAT(d.phoneNumber) as phoneNumber"),
                DB::raw("CASE WHEN lower(d.type)='whatshapp' then true else false end as isWhatsapp"),
                DB::raw("CASE WHEN a.status=1 then 'Active' else 'Non Active' end as status"),
                'e.locationName as location',
                'a.createdBy as createdBy',
                DB::raw(
                    'DATE_FORMAT(a.created_at, "%d-%m-%Y") as createdAt',
                    'a.updated_at'
                )
            )
            ->where([
                ['a.isDeleted', '=', '0'],
                ['b.isActive', '=', '1'],
                ['c.usage', '=', 'Utama'],
                ['c.isDeleted', '=', '0'],
                ['d.usage', '=', 'Utama'],
                ['e.isDeleted', '=', '0'],
            ]);

        $data = DB::table($subquery, 'a');

        if ($request->locationId) {

            $val = [];
            foreach ($request->locationId as $temp) {
                $val = $temp;
            }

            if ($val) {
                $data = $data->whereIn('a.locationid', $request->locationId);
            }
        }

        $data = DB::table($data)
            ->select(
                'id',
                'name',
                'jobTitle',
                'emailAddress',
                'phoneNumber',
                'isWhatsapp',
                'status',
                'location',
                'createdBy',
                'createdAt'
            );

        if ($request->search) {
            $data = $data->where('isWhatsapp', 'like', '%' . $request->search . '%');
        }

        $data = $data->get();

        if (count($data)) {
            $temp_column = 'isWhatsapp';
            return $temp_column;
        }

        $subquery = DB::table('users as a')
            ->leftjoin('jobTitle as b', 'b.id', '=', 'a.jobTitleId')
            ->leftjoin('usersEmails as c', 'c.usersId', '=', 'a.id')
            ->leftjoin('usersTelephones as d', 'd.usersId', '=', 'a.id')
            ->leftjoin('location as e', 'e.id', '=', 'a.locationId')
            ->select(
                'a.id as id',
                DB::raw("CONCAT(IFNULL(a.firstName,'') ,' ', IFNULL(a.middleName,'') ,' ', IFNULL(a.lastName,'') ,'(', IFNULL(a.nickName,a.firstName) ,')'  ) as name"),
                'b.jobName as jobTitle',
                'c.email as emailAddress',
                DB::raw("CONCAT(d.phoneNumber) as phoneNumber"),
                DB::raw("CASE WHEN lower(d.type)='whatshapp' then true else false end as isWhatsapp"),
                DB::raw("CASE WHEN a.status=1 then 'Active' else 'Non Active' end as status"),
                'e.locationName as location',
                'a.createdBy as createdBy',
                DB::raw(
                    'DATE_FORMAT(a.created_at, "%d-%m-%Y") as createdAt',
                    'a.updated_at'
                )
            )
            ->where([
                ['a.isDeleted', '=', '0'],
                ['b.isActive', '=', '1'],
                ['c.usage', '=', 'Utama'],
                ['c.isDeleted', '=', '0'],
                ['d.usage', '=', 'Utama'],
                ['e.isDeleted', '=', '0'],
            ]);


        $data = DB::table($subquery, 'a');

        if ($request->locationId) {

            $val = [];
            foreach ($request->locationId as $temp) {
                $val = $temp;
            }

            if ($val) {
                $data = $data->whereIn('a.locationid', $request->locationId);
            }
        }

        $data = DB::table($data)
            ->select(
                'id',
                'name',
                'jobTitle',
                'emailAddress',
                'phoneNumber',
                'isWhatsapp',
                'status',
                'location',
                'createdBy',
                'createdAt'
            );



        if ($request->search) {
            $data = $data->where('status', 'like', '%' . $request->search . '%');
        }

        $data = $data->get();

        if (count($data)) {
            $temp_column = 'status';
            return $temp_column;
        }


        $subquery = DB::table('users as a')
            ->leftjoin('jobTitle as b', 'b.id', '=', 'a.jobTitleId')
            ->leftjoin('usersEmails as c', 'c.usersId', '=', 'a.id')
            ->leftjoin('usersTelephones as d', 'd.usersId', '=', 'a.id')
            ->leftjoin('location as e', 'e.id', '=', 'a.locationId')
            ->select(
                'a.id as id',
                DB::raw("CONCAT(IFNULL(a.firstName,'') ,' ', IFNULL(a.middleName,'') ,' ', IFNULL(a.lastName,'') ,'(', IFNULL(a.nickName,a.firstName) ,')'  ) as name"),
                'b.jobName as jobTitle',
                'c.email as emailAddress',
                DB::raw("CONCAT(d.phoneNumber) as phoneNumber"),
                DB::raw("CASE WHEN lower(d.type)='whatshapp' then true else false end as isWhatsapp"),
                DB::raw("CASE WHEN a.status=1 then 'Active' else 'Non Active' end as status"),
                'e.locationName as location',
                'a.createdBy as createdBy',
                DB::raw(
                    'DATE_FORMAT(a.created_at, "%d-%m-%Y") as createdAt',
                    'a.updated_at'
                )
            )
            ->where([
                ['a.isDeleted', '=', '0'],
                ['b.isActive', '=', '1'],
                ['c.usage', '=', 'Utama'],
                ['c.isDeleted', '=', '0'],
                ['d.usage', '=', 'Utama'],
                ['e.isDeleted', '=', '0'],
            ]);


        $data = DB::table($subquery, 'a');
        if ($request->locationId) {

            $val = [];
            foreach ($request->locationId as $temp) {
                $val = $temp;
            }

            if ($val) {
                $data = $data->whereIn('a.locationid', $request->locationId);
            }
        }

        $data = DB::table($data)
            ->select(
                'id',
                'name',
                'jobTitle',
                'emailAddress',
                'phoneNumber',
                'isWhatsapp',
                'status',
                'location',
                'createdBy',
                'createdAt'
            );

        if ($request->search) {
            $data = $data->where('location', 'like', '%' . $request->search . '%');
        }

        $data = $data->get();

        if (count($data)) {
            $temp_column = 'location';
            return $temp_column;
        }

        $subquery = DB::table('users as a')
            ->leftjoin('jobTitle as b', 'b.id', '=', 'a.jobTitleId')
            ->leftjoin('usersEmails as c', 'c.usersId', '=', 'a.id')
            ->leftjoin('usersTelephones as d', 'd.usersId', '=', 'a.id')
            ->leftjoin('location as e', 'e.id', '=', 'a.locationId')
            ->select(
                'a.id as id',
                DB::raw("CONCAT(IFNULL(a.firstName,'') ,' ', IFNULL(a.middleName,'') ,' ', IFNULL(a.lastName,'') ,'(', IFNULL(a.nickName,a.firstName) ,')'  ) as name"),
                'b.jobName as jobTitle',
                'c.email as emailAddress',
                DB::raw("CONCAT(d.phoneNumber) as phoneNumber"),
                DB::raw("CASE WHEN lower(d.type)='whatshapp' then true else false end as isWhatsapp"),
                DB::raw("CASE WHEN a.status=1 then 'Active' else 'Non Active' end as status"),
                'e.locationName as location',
                'a.createdBy as createdBy',
                DB::raw(
                    'DATE_FORMAT(a.created_at, "%d-%m-%Y") as createdAt',
                    'a.updated_at'
                )
            )
            ->where([
                ['a.isDeleted', '=', '0'],
                ['b.isActive', '=', '1'],
                ['c.usage', '=', 'Utama'],
                ['c.isDeleted', '=', '0'],
                ['d.usage', '=', 'Utama'],
                ['e.isDeleted', '=', '0'],
            ]);

        $data = DB::table($subquery, 'a');
        if ($request->locationId) {

            $val = [];
            foreach ($request->locationId as $temp) {
                $val = $temp;
            }

            if ($val) {
                $data = $data->whereIn('a.locationid', $request->locationId);
            }
        }

        $data = DB::table($data)
            ->select(
                'id',
                'name',
                'jobTitle',
                'emailAddress',
                'phoneNumber',
                'isWhatsapp',
                'status',
                'location',
                'createdBy',
                'createdAt'
            );

        if ($request->search) {
            $data = $data->where('createdBy', 'like', '%' . $request->search . '%');
        }

        $data = $data->get();

        if (count($data)) {
            $temp_column = 'createdBy';
            return $temp_column;
        }

        $subquery = DB::table('users as a')
            ->leftjoin('jobTitle as b', 'b.id', '=', 'a.jobTitleId')
            ->leftjoin('usersEmails as c', 'c.usersId', '=', 'a.id')
            ->leftjoin('usersTelephones as d', 'd.usersId', '=', 'a.id')
            ->leftjoin('location as e', 'e.id', '=', 'a.locationId')
            ->select(
                'a.id as id',
                DB::raw("CONCAT(IFNULL(a.firstName,'') ,' ', IFNULL(a.middleName,'') ,' ', IFNULL(a.lastName,'') ,'(', IFNULL(a.nickName,a.firstName) ,')'  ) as name"),
                'b.jobName as jobTitle',
                'c.email as emailAddress',
                DB::raw("CONCAT(d.phoneNumber) as phoneNumber"),
                DB::raw("CASE WHEN lower(d.type)='whatshapp' then true else false end as isWhatsapp"),
                DB::raw("CASE WHEN a.status=1 then 'Active' else 'Non Active' end as status"),
                'e.locationName as location',
                'a.createdBy as createdBy',
                DB::raw(
                    'DATE_FORMAT(a.created_at, "%d-%m-%Y") as createdAt',
                    'a.updated_at'
                )
            )
            ->where([
                ['a.isDeleted', '=', '0'],
                ['b.isActive', '=', '1'],
                ['c.usage', '=', 'Utama'],
                ['c.isDeleted', '=', '0'],
                ['d.usage', '=', 'Utama'],
                ['e.isDeleted', '=', '0'],
            ]);

        $data = DB::table($subquery, 'a');
        if ($request->locationId) {

            $val = [];
            foreach ($request->locationId as $temp) {
                $val = $temp;
            }

            if ($val) {
                $data = $data->whereIn('a.locationid', $request->locationId);
            }
        }

        $data = DB::table($data)
            ->select(
                'id',
                'name',
                'jobTitle',
                'emailAddress',
                'phoneNumber',
                'isWhatsapp',
                'status',
                'location',
                'createdBy',
                'createdAt'
            );

        if ($request->search) {
            $data = $data->where('createdAt', 'like', '%' . $request->search . '%');
        }

        $data = $data->get();

        if (count($data)) {
            $temp_column = 'createdAt';
            return $temp_column;
        }
    }


    public function uploadImageStaff(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'id' => 'required',
        ]);

        if ($validate->fails()) {

            $errors = $validate->errors()->all();

            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $errors,
            ], 422);
        }

        $checkIfValueExits = DB::table('users')
            ->where([
                ['id', '=', $request->id],
                ['isDeleted', '=', 0],
            ])
            ->first();

        if ($checkIfValueExits === null) {

            return response()->json([
                'message' => 'Failed',
                'errors' => "Data not exists, please try another user id",
            ], 406);
        } else {
            
            $checkImages = DB::table('usersImages')
                ->where([
                    ['usersId', '=', $request->id],
                    ['isDeleted', '=', 0],
                ])
                ->first();


            if ($checkImages) {

                File::delete(public_path() . $checkImages->imagePath);

                DB::table('usersImages')->where([
                    ['usersId', '=', $request->id],
                ])->delete();
            }



            if ($request->hasfile('image')) {

                $files = $request->file('image');

                $name = $files->hashName();
                $files->move(public_path() . '/UsersImages/', $name);

                $fileName = "/UsersImages/" . $name;

                DB::table('usersImages')
                    ->insert([
                        'usersId' => $request->id,
                        'imagePath' => $fileName,
                        'isDeleted' => 0,
                        'created_at' => now(),
                    ]);

                DB::commit();

                return response()->json(
                    [
                        'result' => 'success',
                        'message' => 'Upload image users Success!',
                    ],
                    200
                );
            }
        }
    }


    public function getDetailStaff(Request $request)
    {

        try {

            $validate = Validator::make($request->all(), [
                'id' => 'required',
            ]);

            if ($validate->fails()) {

                $errors = $validate->errors()->all();

                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => $errors,
                ], 422);
            }

            $checkIfValueExits = DB::table('users')
                ->where([
                    ['id', '=', $request->id],
                    ['isDeleted', '=', 0],
                ])
                ->first();

            if ($checkIfValueExits === null) {

                return response()->json([
                    'message' => 'Failed',
                    'errors' => "Data not exists, please try another user id",
                ], 406);
            } else {
                

                $users = DB::table('users as a')
                    ->leftjoin('location as b', 'b.id', '=', 'a.locationId')
                    ->leftjoin('jobTitle as c', 'c.id', '=', 'a.jobTitleId')
                    ->leftjoin('typeId as d', 'd.id', '=', 'a.typeId')
                    ->leftjoin('payPeriod as e', 'e.id', '=', 'a.payPeriodId')
                    ->select(
                        'a.id',
                        'a.firstName',
                        'a.middleName',
                        'a.lastName',
                        'a.nickName',
                        'a.gender',
                        'a.status',
                        'c.id as jobTitleId',
                        'c.jobName as jobName',
                        'a.startDate',
                        'a.endDate',
                        'a.registrationNo',
                        'a.designation',
                        'b.id as locationId',
                        'b.locationname as locationName',

                        'a.annualSickAllowance',
                        'a.annualLeaveAllowance',
                        'a.payPeriodId',
                        'e.periodName',
                        'a.payAmount',

                        'd.id as typeId',
                        'd.typeName as typeName',
                        'a.identificationNumber',
                        'a.additionalInfo',

                        'a.generalCustomerCanSchedule',
                        'a.generalCustomerReceiveDailyEmail',
                        'a.generalAllowMemberToLogUsingEmail',
                        'a.reminderEmail',
                        'a.reminderWhatsapp',
                        'a.roleId',

                    )
                    ->where([
                        ['a.id', '=', $request->id],
                        ['a.isDeleted', '=', '0'],
                        ['c.isActive', '=', '1'],
                        ['d.isActive', '=', '1'],
                        ['e.isActive', '=', '1']
                    ])
                    ->first();


                $usersimages = DB::table('usersImages as a')
                    ->select(
                        'a.id as id',
                        'a.usersId as usersId',
                        'a.imagePath as imagePath',
                    )
                    ->where([
                        ['a.usersId', '=', $request->id],
                        ['a.isDeleted', '=', '0']
                    ])
                    ->get();

                $users->images = $usersimages;

                $users_detail_address = DB::table('usersDetailAddresses as a')
                    ->select(
                        'a.addressName as addressName',
                        'a.additionalInfo as additionalInfo',
                        'a.provinceCode as provinceCode',
                        'a.cityCode as cityCode',
                        'a.postalCode as postalCode',
                        'a.country as country',
                        'a.isPrimary as isPrimary',
                    )
                    ->where([
                        ['a.usersId', '=', $request->id],
                        ['a.isDeleted', '=', '0']
                    ])
                    ->get();

                $users->detailAddress = $users_detail_address;


                $usersmessengers = DB::table('usersMessengers as a')
                    ->select(
                        'a.messengerNumber as messengerNumber',
                        'a.type as type',
                        'a.usage as usage',
                    )
                    ->where([
                        ['a.usersId', '=', $request->id],
                        ['a.isDeleted', '=', '0']
                    ])
                    ->get();

                $users->messenger = $usersmessengers;

                $usersEmails = DB::table('usersEmails as a')
                    ->select(
                        'a.email as email',
                        'a.usage as usage',
                    )
                    ->where([
                        ['a.usersId', '=', $request->id],
                        ['a.isDeleted', '=', '0']
                    ])
                    ->get();

                $users->email = $usersEmails;

                $userstelephone = DB::table('usersTelephones as a')
                    ->select(
                        'a.phoneNumber as phoneNumber',
                        'a.type as type',
                        'a.usage as usage',
                    )
                    ->where([
                        ['a.usersId', '=', $request->id],
                        ['a.isDeleted', '=', '0']
                    ])
                    ->get();

                $users->telephone = $userstelephone;

                return response()->json($users, 200);
            }
        } catch (Exception $e) {

            DB::rollback();

            return response()->json([
                'message' => 'failed',
                'errors' => $e,
            ], 422);
        }
    }

    

    public function exportStaff(Request $request)
    {

        try {

            $tmp = "";
            $fileName = "";
            $date = Carbon::now()->format('d-m-Y');

            if ($request->locationId) {

                $location = DB::table('location')
                    ->select('locationName')
                    ->whereIn('id', $request->locationId)
                    ->get();

                if ($location) {

                    foreach ($location as $key) {
                        $tmp = $tmp . (string) $key->locationName . ",";
                    }
                }
                $tmp = rtrim($tmp, ", ");
            }

            if ($tmp == "") {
                $fileName = "Staff " . $date . ".xlsx";
            } else {
                $fileName = "Staff " . $tmp . " " . $date . ".xlsx";
            }

            return Excel::download(
                new exportStaff(
                    $request->orderValue,
                    $request->orderColumn,
                    $request->locationId,
                ),
                $fileName
            );
        } catch (Exception $e) {

            DB::rollback();

            return response()->json([
                'message' => 'Failed',
                'errors' => $e,
            ]);
        }
    }

    
    public function updateStaff(Request $request)
    {


        if (adminAccess($request->user()->id) != 1) {
            return response()->json([
                'message' => 'The user role was invalid.',
                'errors' => ['User Access not Authorize!'],
            ], 403);
        }

        DB::beginTransaction();

        try {

            $validate = Validator::make(
                $request->all(),
                [
                    'id' => 'required',
                    'firstName' => 'required|max:20|min:3',
                    'middleName' => 'max:20|min:3|nullable',
                    'lastName' => 'max:20|min:3|nullable',
                    'nickName' => 'max:20|min:3|nullable',
                    'gender' => 'string|nullable',
                    'status' => 'required|integer',
                    'jobTitleId' => 'required|integer',
                    'startDate' => 'required|date',
                    'endDate' => 'required|date|after:startDate',
                    'registrationNo' => 'string|max:20|min:5|nullable',
                    'designation' => 'string|max:20|min:5|nullable',
                    'locationId' => 'required|integer',
                    'annualSickAllowance' => 'integer|nullable',
                    'annualLeaveAllowance' => 'integer|nullable',
                    'payPeriodId' => 'required|integer',
                    'payAmount' => 'numeric|nullable',
                    'typeId' => 'required|integer',
                    'identificationNumber' => 'string|nullable|max:30',
                    'additionalInfo' => 'string|nullable|max:100',
                    'generalCustomerCanSchedule' => 'integer|nullable',
                    'generalCustomerReceiveDailyEmail' => 'integer|nullable',
                    'generalAllowMemberToLogUsingEmail' => 'integer|nullable',
                    'roleId' => 'required|integer',
                    'reminderEmail' => 'integer|nullable',
                    'reminderWhatsapp' => 'integer|nullable',
                ]
            );

            if ($validate->fails()) {
                $errors = $validate->errors()->all();
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => $errors,
                ], 422);
            }
            
            $checkIfUsersExists = DB::table('users')
                ->where([
                    ['id', '=', $request->id],
                    ['isDeleted', '=', '0']
                ])
                ->first();

            if (!$checkIfUsersExists) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => ['Spesific users not exists please try different id!'],
                ], 422);
            }
            

            if ($request->typeId == 3) {

                if ((is_numeric($request->identificationNumber))) {

                    return response()->json([
                        'message' => 'The given data was invalid.',
                        'errors' => "Identification number must be alpanumeric if identification type is passport!",
                    ], 422);

                }
            } else {

                if (!is_numeric($request->identificationNumber) && is_int((int)$request->identificationNumber)) {

                    return response()->json([
                        'message' => 'The given data was invalid.',
                        'errors' => "Identification number must be integer!",
                    ], 422);
                }
            }


            $data_error_detailaddress = [];

            if ($request->detailAddress) {

                $messageAddress = [
                    'addressName.required' => 'Address name on tab Address is required',
                    'provinceCode.required' => 'Province code on tab Address is required',
                    'cityCode.required' => 'City code on tab Address is required',
                    'country.required' => 'Country on tab Address is required',
                ];


                $primaryCount = 0;
                foreach ($request->detailAddress as $item) {
                    if (isset($item['isPrimary']) && $item['isPrimary'] == 1) {
                        $primaryCount++;
                    }
                }

                if ($primaryCount == 0) {
                    return response()->json([
                        'message' => 'Inputed data is not valid',
                        'errors' => 'Detail address must have at least 1 primary address',
                    ], 422);
                } elseif ($primaryCount > 1) {
                    return response()->json([
                        'message' => 'Inputed data is not valid',
                        'errors' => 'Detail address have 2 primary address, please check again',
                    ], 422);
                }


                foreach ($request->detailAddress as $key) {

                    $validateDetail = Validator::make(
                        $key,
                        [
                            'addressName' => 'required',
                            'provinceCode' => 'required',
                            'cityCode' => 'required',
                            'country' => 'required',
                        ],
                        $messageAddress
                    );

                    if ($validateDetail->fails()) {

                        $errors = $validateDetail->errors()->all();

                        foreach ($errors as $checkisu) {

                            if (!(in_array($checkisu, $data_error_detailaddress))) {
                                array_push($data_error_detailaddress, $checkisu);
                            }
                        }
                    }
                }
                
                if ($data_error_detailaddress) {
                    return response()->json([
                        'message' => 'The given data was invalid.',
                        'errors' => $data_error_detailaddress,
                    ], 422);
                }
            } else {

                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => ['Detail address can not be empty!'],
                ], 422);
            }


            $data_error_telephone = [];

            if ($request->telephone) {

                $messagePhone = [
                    'phoneNumber.required' => 'Phone Number on tab telephone is required',
                    'type.required' => 'Type on tab telephone is required',
                    'usage.required' => 'Usage on tab telephone is required',
                ];

                foreach ($request->telephone as $key) {

                    $telephoneDetail = Validator::make(
                        $key,
                        [
                            'phoneNumber' => 'required',
                            'type' => 'required',
                            'usage' => 'required',
                        ],
                        $messagePhone
                    );

                    if ($telephoneDetail->fails()) {

                        $errors = $telephoneDetail->errors()->all();

                        foreach ($errors as $checkisu) {

                            if (!(in_array($checkisu, $data_error_telephone))) {
                                array_push($data_error_telephone, $checkisu);
                            }
                        }
                    }


                    if (strtolower($key['type']) == "whatshapp") {

                        if (!(substr($key['phoneNumber'], 0, 2) === "62")) {

                            return response()->json([
                                'message' => 'Failed',
                                'errors' => 'Please check your phone number, for type whatshapp must start with 62',
                            ], 422);
                        }
                    }
                }

                

                if ($data_error_telephone) {
                    return response()->json([
                        'message' => 'The given data was invalid.',
                        'errors' => $data_error_telephone,
                    ], 422);
                }

                $checkTelephone = [];

                foreach ($request->telephone as $val) {

                    $checkIfTelephoneAlreadyExists = DB::table('usersTelephones')
                        ->where([
                            ['phoneNumber', '=', $val['phoneNumber'],],
                            ['isDeleted', '=', '0'],
                            ['usersId', '!=', $request->id]
                        ])
                        ->first();

                    if ($checkIfTelephoneAlreadyExists) {
                        array_push($checkTelephone, 'Phonenumber : ' . $val['phoneNumber'] . ' already exists, please try different number');
                    }
                }

                if ($checkTelephone) {
                    return response()->json([
                        'message' => 'Inputed data is not valid',
                        'errors' => $checkTelephone,
                    ], 422);
                }
            }
            
            $data_error_email = [];
            $insertEmailUsers = '';

            if ($request->email) {

                $messageEmail = [
                    'email.required' => 'Email on tab email is required',
                    'usage.required' => 'Usage on tab email is required',
                ];

                foreach ($request->email as $key) {

                    $emailDetail = Validator::make(
                        $key,
                        [
                            'email' => 'required',
                            'usage' => 'required',
                        ],
                        $messageEmail
                    );


                    if ($emailDetail->fails()) {

                        $errors = $emailDetail->errors()->all();

                        foreach ($errors as $checkisu) {

                            if (!(in_array($checkisu, $data_error_email))) {
                                array_push($data_error_email, $checkisu);
                            }
                        }
                    }
                }


                if ($data_error_email) {
                    return response()->json([
                        'message' => 'The given data was invalid.',
                        'errors' => $data_error_email,
                    ], 422);
                }
                

                $checkEmail = [];
                $checkUsageEmail = false;

                foreach ($request->email as $val) {

                    $checkIfEmailExists = DB::table('usersEmails')
                        ->where([
                            ['email', '=', $val['email'],],
                            ['isDeleted', '=', '0'],
                            ['usersId', '!=', $request->id]
                        ])
                        ->first();

                    if ($checkIfEmailExists) {
                        array_push($checkEmail, 'Email : ' . $val['email'] . ' already exists, please try different email address');
                    }


                    if ($val['usage'] == 'Utama') {
                        $checkUsageEmail = true;

                        $checkEmailUtama = DB::table('usersEmails')
                            ->where([
                                ['usage', '=', 'Utama'],
                                ['isDeleted', '=', '0'],
                                ['usersId', '=', $request->id]
                            ])
                            ->first();

                        if ($checkEmailUtama->email != $val['email']) {

                            $insertEmailUsers = $val['email'];
                        }
                    }
                }

                if ($checkEmail) {
                    return response()->json([
                        'message' => 'Inputed data is not valid',
                        'errors' => $checkEmail,
                    ], 422);
                }
                
                if ($checkUsageEmail == false) {

                    return response()->json([
                        'message' => 'Inputed data is not valid',
                        'errors' => 'Must have one primary email',
                    ], 422);
                }
            }


            $data_messenger_error = [];
            if ($request->messenger) {

                $messageMessenger = [
                    'messengerNumber.required' => 'messenger number on tab messenger is required',
                    'type.required' => 'Type on tab messenger is required',
                    'usage.required' => 'Usage on tab messenger is required',
                ];

                foreach ($request->messenger as $key) {

                    $messengerDetail = Validator::make(
                        $key,
                        [
                            'messengerNumber' => 'required',
                            'type' => 'required',
                            'usage' => 'required',
                        ],
                        $messageMessenger
                    );


                    if ($messengerDetail->fails()) {

                        $errors = $messengerDetail->errors()->all();

                        foreach ($errors as $checkisu) {

                            if (!(in_array($checkisu, $data_messenger_error))) {
                                array_push($data_messenger_error, $checkisu);
                            }
                        }
                    }

                    if (strtolower($key['type']) == "whatshapp") {

                        if (!(substr($key['messengerNumber'], 0, 2) === "62")) {
                            return response()->json([
                                'message' => 'Failed',
                                'errors' => 'Please check your phone number, for type whatshapp must start with 62',
                            ], 422);
                        }
                    }
                }
                
                if ($data_messenger_error) {

                    return response()->json([
                        'message' => 'Failed',
                        'errors' => $data_messenger_error,
                    ], 422);
                }

                $checkMessenger = [];

                foreach ($request->messenger as $val) {

                    $checkifMessengerExists = DB::table('usersMessengers')
                        ->where([
                            ['messengerNumber', '=', $val['messengerNumber'],],
                            ['isDeleted', '=', '0'],
                            ['usersId', '!=', $request->id]
                        ])
                        ->first();

                    if ($checkifMessengerExists) {
                        array_push($checkMessenger, 'Messenger number  : ' . $val['messengerNumber'] . ' already exists, please try different number');
                    }
                }

                if ($checkMessenger) {

                    return response()->json([
                        'message' => 'Failed',
                        'errors' => $checkMessenger,
                    ], 422);
                }
            }
            
            $start = Carbon::parse($request->startDate);
            $end = Carbon::parse($request->endDate);

            if ($insertEmailUsers) {

                DB::table('users')
                    ->where('id', '=', $request->id)
                    ->update([
                        'firstName' => $request->firstName,
                        'middleName' => $request->middleName,
                        'lastName' => $request->lastName,
                        'nickName' => $request->nickName,
                        'gender' => $request->gender,
                        'status' => $request->status,
                        'jobTitleId' => $request->jobTitleId,
                        'startDate' => $start,
                        'endDate' => $end,
                        'registrationNo' => $request->registrationNo,
                        'designation' => $request->designation,
                        'locationId' => $request->locationId,
                        'annualSickAllowance' => $request->annualSickAllowance,
                        'annualSickAllowanceRemaining' => $request->annualSickAllowance,
                        'annualLeaveAllowance' => $request->annualLeaveAllowance,
                        'annualLeaveAllowanceRemaining' => $request->annualLeaveAllowance,
                        'payPeriodId' => $request->payPeriodId,
                        'payAmount' => $request->payAmount,
                        'typeId' => $request->typeId,
                        'identificationNumber' => $request->identificationNumber,
                        'additionalInfo' => $request->additionalInfo,
                        'generalCustomerCanSchedule' => $request->generalCustomerCanSchedule,
                        'generalCustomerReceiveDailyEmail' => $request->generalCustomerReceiveDailyEmail,
                        'generalAllowMemberToLogUsingEmail' => $request->generalAllowMemberToLogUsingEmail,
                        'reminderEmail' => $request->reminderEmail,
                        'reminderWhatsapp' => $request->reminderWhatsapp,
                        'roleId' => $request->roleId,
                        'createdBy' => $request->user()->firstName,
                        'updated_at' => now(),
                        'password' => null,
                        'email' => $insertEmailUsers,
                    ]);

                if ($request->detailAddress) {

                    DB::table('usersDetailAddresses')->where('usersId', '=', $request->id)->delete();

                    foreach ($request->detailAddress as $val) {

                        DB::table('usersDetailAddresses')
                            ->insert([
                                'usersId' => $request->id,
                                'addressName' => $val['addressName'],
                                'additionalInfo' => $val['additionalInfo'],
                                'provinceCode' => $val['provinceCode'],
                                'cityCode' => $val['cityCode'],
                                'postalCode' => $val['postalCode'],
                                'country' => $val['country'],
                                'isPrimary' => $val['isPrimary'],
                                'isDeleted' => 0,
                                'updated_at' => now(),
                            ]);
                    }
                }

                if ($request->messenger) {

                    DB::table('usersMessengers')->where('usersId', '=', $request->id)->delete();

                    foreach ($request->messenger as $val) {
                        DB::table('usersMessengers')
                            ->insert([
                                'usersId' => $request->id,
                                'messengerNumber' => $val['messengerNumber'],
                                'type' => $val['type'],
                                'usage' => $val['usage'],
                                'isDeleted' => 0,
                                'updated_at' => now(),
                            ]);
                    }
                }

                if ($request->email) {

                    DB::table('usersEmails')->where('usersId', '=', $request->id)->delete();

                    foreach ($request->email as $val) {
                        DB::table('usersEmails')
                            ->insert([
                                'usersId' => $request->id,
                                'email' => $val['email'],
                                'usage' => $val['usage'],
                                'isDeleted' => 0,
                                'updated_at' => now(),
                            ]);
                    }
                }



                if ($request->telephone) {

                    DB::table('usersTelephones')->where('usersId', '=', $request->id)->delete();

                    foreach ($request->telephone as $val) {
                        DB::table('usersTelephones')
                            ->insert([
                                'usersId' => $request->id,
                                'phoneNumber' => $val['phoneNumber'],
                                'type' => $val['type'],
                                'usage' => $val['usage'],
                                'isDeleted' => 0,
                                'updated_at' => now(),
                            ]);
                    }
                }

                if ($request->status == 0) {

                    DB::commit();
                    return response()->json([
                        'result' => 'success',
                        'message' => 'successfuly update user ',
                    ]);
                } else {


                    $jobtitleName = DB::table('jobTitle')
                        ->select('jobName')
                        ->where([
                            ['id', '=', $request->jobTitleId],
                            ['isActive', '=', 1]
                        ])
                        ->first();


                    $data = [
                        'subject' => 'RPC Petshop',
                        'body' => 'Please verify your account',
                        'isi' => 'This e-mail was sent from a notification-only address that cannot accept incoming e-mails. Please do not reply to this message.',
                        'name' => $request->firstName,
                        'email' => $insertEmailUsers,
                        'jobTitle' => $jobtitleName->jobName,
                        'usersId' => $request->id,
                    ];

                    Mail::to($insertEmailUsers)->send(new SendEmail($data));

                    DB::commit();

                    return response()->json([
                        'result' => 'success',
                        'message' => 'successfuly update user, your primary email has updated, please check your new email to verify your password',
                    ]);
                }
            } else {


                DB::table('users')
                    ->where('id', '=', $request->id)
                    ->update([
                        'firstName' => $request->firstName,
                        'middleName' => $request->middleName,
                        'lastName' => $request->lastName,
                        'nickName' => $request->nickName,
                        'gender' => $request->gender,
                        'status' => $request->status,
                        'jobTitleId' => $request->jobTitleId,
                        'startDate' => $start,
                        'endDate' => $end,
                        'registrationNo' => $request->registrationNo,
                        'designation' => $request->designation,
                        'locationId' => $request->locationId,
                        'annualSickAllowance' => $request->annualSickAllowance,
                        'annualSickAllowanceRemaining' => $request->annualSickAllowance,
                        'annualLeaveAllowance' => $request->annualLeaveAllowance,
                        'annualLeaveAllowanceRemaining' => $request->annualLeaveAllowance,
                        'payPeriodId' => $request->payPeriodId,
                        'payAmount' => $request->payAmount,
                        'typeId' => $request->typeId,
                        'identificationNumber' => $request->identificationNumber,
                        'additionalInfo' => $request->additionalInfo,
                        'generalCustomerCanSchedule' => $request->generalCustomerCanSchedule,
                        'generalCustomerReceiveDailyEmail' => $request->generalCustomerReceiveDailyEmail,
                        'generalAllowMemberToLogUsingEmail' => $request->generalAllowMemberToLogUsingEmail,
                        'reminderEmail' => $request->reminderEmail,
                        'reminderWhatsapp' => $request->reminderWhatsapp,
                        'roleId' => $request->roleId,
                        'createdBy' => $request->user()->firstName,
                        'updated_at' => now(),

                    ]);


                if ($request->detailAddress) {

                    DB::table('usersDetailAddresses')->where('usersId', '=', $request->id)->delete();

                    foreach ($request->detailAddress as $val) {

                        DB::table('usersDetailAddresses')
                            ->insert([
                                'usersId' => $request->id,
                                'addressName' => $val['addressName'],
                                'additionalInfo' => $val['additionalInfo'],
                                'provinceCode' => $val['provinceCode'],
                                'cityCode' => $val['cityCode'],
                                'postalCode' => $val['postalCode'],
                                'country' => $val['country'],
                                'isPrimary' => $val['isPrimary'],
                                'isDeleted' => 0,
                                'updated_at' => now(),
                            ]);
                    }
                }


                if ($request->messenger) {

                    DB::table('usersMessengers')->where('usersId', '=', $request->id)->delete();

                    foreach ($request->messenger as $val) {
                        DB::table('usersMessengers')
                            ->insert([
                                'usersId' => $request->id,
                                'messengerNumber' => $val['messengerNumber'],
                                'type' => $val['type'],
                                'usage' => $val['usage'],
                                'isDeleted' => 0,
                                'updated_at' => now(),
                            ]);
                    }
                }

                if ($request->email) {

                    DB::table('usersEmails')->where('usersId', '=', $request->id)->delete();

                    foreach ($request->email as $val) {
                        DB::table('usersEmails')
                            ->insert([
                                'usersId' => $request->id,
                                'email' => $val['email'],
                                'usage' => $val['usage'],
                                'email_verified_at' => now(),
                                'isDeleted' => 0,
                                'updated_at' => now(),
                            ]);
                    }
                }

                if ($request->telephone) {

                    DB::table('usersTelephones')->where('usersId', '=', $request->id)->delete();

                    foreach ($request->telephone as $val) {
                        DB::table('usersTelephones')
                            ->insert([
                                'usersId' => $request->id,
                                'phoneNumber' => $val['phoneNumber'],
                                'type' => $val['type'],
                                'usage' => $val['usage'],
                                'isDeleted' => 0,
                                'updated_at' => now(),
                            ]);
                    }
                }

                DB::commit();

                return response()->json([
                    'result' => 'success',
                    'message' => 'successfuly update user',
                ]);
            }
        } catch (Exception $e) {

            DB::rollback();

            return response()->json([
                'message' => 'failed',
                'errors' => $e,
            ], 422);
        }
    }
    

    public function getTypeId(Request $request)
    {

        try {

            $getTypeId = DB::table('typeId as a')
                ->select(
                    'a.id as typeId',
                    'a.typeName as typeName',
                )
                ->where([
                    ['isActive', '=', 1],
                ])
                ->orderBy('a.created_at', 'desc')
                ->get();

            return response()->json($getTypeId, 200);
        } catch (Exception $e) {

            return response()->json([
                'message' => 'Failed',
                'errors' => $e,
            ], 422);
        }
    }
    

    public function getPayPeriod(Request $request)
    {

        try {

            $getPayPeriod = DB::table('payPeriod as a')
                ->select(
                    'a.id as payPeriodId',
                    'a.periodName as periodName',
                )
                ->where([
                    ['isActive', '=', 1],
                ])
                ->orderBy('a.created_at', 'desc')
                ->get();

            return response()->json($getPayPeriod, 200);
        } catch (Exception $e) {

            return response()->json([
                'message' => 'Failed',
                'errors' => $e,
            ], 422);
        }
    }
    
    public function getJobTitle(Request $request)
    {

        try {

            $getjobTitle = DB::table('jobTitle as a')
                ->select(
                    'a.id as jobTitleid',
                    'a.jobName as jobName',
                )
                ->where([
                    ['isActive', '=', 1],
                ])
                ->orderBy('a.created_at', 'desc')
                ->get();

            return response()->json($getjobTitle, 200);
        } catch (Exception $e) {

            return response()->json([
                'message' => 'Failed',
                'errors' => $e,
            ], 422);
        }
    }

    
    public function insertTypeId(Request $request)
    {

        $request->validate([
            'typeName' => 'required|string',
        ]);

        DB::beginTransaction();

        try {

            $checkIfValueExits = DB::table('typeId as a')
                ->where([
                    ['a.typeName', '=', $request->typeName],
                    ['a.isActive', '=', 1]
                ])
                ->first();

            if ($checkIfValueExits != null) {
              
                return response()->json([
                    'message' => 'Failed',
                    'errors' => 'Type name already exists, please choose another name',
                ]);
            } else {

                DB::table('typeId')->insert([
                    'typeName' => $request->typeName,
                    'created_at' => now(),
                    'isActive' => 1,
                ]);

                DB::commit();

                return response()->json([
                    'result' => 'success',
                    'message' => 'Successfully inserted Type ID',
                ]);
            }
        } catch (Exception $e) {

            DB::rollback();

            return response()->json([
                'message' => 'failed',
                'errors' => $e,
            ], 422);
        }
    }
    


    public function insertJobTitle(Request $request)
    {

        $request->validate([
            'jobName' => 'required|string',
        ]);

        DB::beginTransaction();

        try {

            $checkIfValueExits = DB::table('jobTitle as a')
                ->where([
                    ['a.jobName', '=', $request->jobName],
                    ['a.isActive', '=', 1]
                ])
                ->first();

            if ($checkIfValueExits != null) {

                return response()->json([
                    'message' => 'Failed',
                    'errors' => 'Job title already exists, please choose another name',
                ]);
                
            } else {

                DB::table('jobTitle')->insert([
                    'jobName' => $request->jobName,
                    'created_at' => now(),
                    'isActive' => 1,
                ]);

                DB::commit();

                return response()->json([
                    'message' => 'success',
                    'errors' => 'Successfully inserted Job Title',
                ]);

                
            }
        } catch (Exception $e) {

            DB::rollback();

            return response()->json([
                'message' => 'failed',
                'errors' => $e,
            ], 422);
        }
    }



    public function insertPayPeriod(Request $request)
    {

        $request->validate([
            'periodName' => 'required|string',
        ]);

        DB::beginTransaction();

        try {

            $checkIfValueExits = DB::table('payPeriod as a')
                ->where([
                    ['a.periodName', '=', $request->periodName],
                    ['a.isActive', '=', 1]
                ])
                ->first();

            if ($checkIfValueExits != null) {

                return response()->json([
                    'message' => 'Failed',
                    'errors' => 'Pay period already exists, please choose another name',
                ]);

                
            } else {

                DB::table('payPeriod')->insert([
                    'periodName' => $request->periodName,
                    'created_at' => now(),
                    'isActive' => 1,
                ]);

                DB::commit();

                return response()->json([
                    'result' => 'success',
                    'message' => 'Successfully inserted pay period',
                ]);
            }
        } catch (Exception $e) {

            DB::rollback();

            return response()->json([
                'message' => 'failed',
                'errors' => $e,
            ], 422);
        }
    }

    public function deleteStaff(Request $request)
    {

        if (adminAccess($request->user()->id) != 1) {
            return response()->json([
                'message' => 'The user role was invalid.',
                'errors' => ['User Access not Authorize!'],
            ], 403);
        }

        $validate = Validator::make($request->all(), [
            'id' => 'required',
        ]);

        if ($validate->fails()) {
            $errors = $validate->errors()->all();
            
            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $errors,
            ], 422);
        }

        DB::beginTransaction();
        try {


            foreach ($request->id as $val) {

                $checkIfDataExits = DB::table('users')
                    ->where([
                        ['id', '=', $val],
                        ['isDeleted', '=', 0],
                    ])
                    ->first();

                if (!$checkIfDataExits) {
                    return response()->json([
                        'message' => 'The given data was invalid.',
                        'errors' => ['Data not found! try different ID'],
                    ], 422);
                }
            }

            foreach ($request->id as $val) {

                DB::table('users')
                    ->where('id', '=', $val)
                    ->update(['isDeleted' => 1]);

                DB::table('usersDetailAddresses')
                    ->where('usersId', '=', $val)
                    ->update(['isDeleted' => 1]);

                DB::table('usersEmails')
                    ->where('usersId', '=', $val)
                    ->update(['isDeleted' => 1]);

                DB::table('usersMessengers')
                    ->where('usersId', '=', $val)
                    ->update(['isDeleted' => 1]);

                DB::table('usersImages')
                    ->where('usersId', '=', $val)
                    ->update(['isDeleted' => 1]);

                DB::table('usersTelephones')
                    ->where('usersId', '=', $val)
                    ->update(['isDeleted' => 1]);


                $checkImages = DB::table('usersImages')
                    ->where([
                        ['usersId', '=', $val]
                    ])
                    ->first();

                if ($checkImages != null) {

                    File::delete(public_path() . $checkImages->imagePath);
                }

                DB::commit();
            }

            return response()->json([
                'result' => 'success',
                'message' => 'Successfully deleted user',
            ], 200);
        } catch (Exception $e) {

            DB::rollback();

            return response()->json([
                'message' => 'failed',
                'errors' => $e,
            ], 422);
        }
    }
    
    public function staffListTransferProduct(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'locationId' => 'required|integer',
        ]);

        if ($validate->fails()) {
            $errors = $validate->errors()->all();

            return response()->json([
                'message' => 'The given data was invalid.',
                'errors' => $errors,
            ], 422);
        }

        $data = DB::table('users as u')
            ->join('location as l', 'u.locationId', 'l.id')
            ->select('u.id', 'u.firstName as name')
            ->where('u.isDeleted', '=', 0)
            ->where('u.locationId', '=', $request->locationId)
            ->where('u.id', '<>', $request->user()->id)
            ->orderBy('u.created_at', 'desc')
            ->get();
        return response()->json($data, 200);
    }

    public function listStaff()
    {
        $data = DB::table('users')
            ->select(
                'id',
                DB::raw("CONCAT(firstName,' ',middleName,CASE WHEN middleName = '' THEN '' ELSE ' ' END,lastName) as fullName")
            )
            ->where('isDeleted', '=', 0)
            ->get();

        return response()->json($data, 200);
    }
}
