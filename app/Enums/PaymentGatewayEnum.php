<?php
namespace App\Enums;

class PaymentGatewayEnum
{
    const VAL_NOTREGISTER = 'client_not_register';
    const VAL_INVALIDREQUEST = 'invalid_request_validation';

    const STEP_MODELVALIDATION = 'model_validation';
    const STEP_MODELNOTFOUND = 'model_not_found';
    const STEP_ERRORLOGIN = 'login_error';
    const STEP_PAY = 'pay';
    const STEP_CREATEPADSOLICITUDES = 'create_pad_solicitudes';

}
