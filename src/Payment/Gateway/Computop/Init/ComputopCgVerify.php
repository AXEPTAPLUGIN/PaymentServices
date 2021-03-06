<?php

namespace Payment\Gateway\Computop\Init;
use Payment\Gateway\Computop\BaseComputopCg;
use Payment\Gateway\Computop\ComputopUtils;

class ComputopCgVerify extends BaseComputopCg {
    public $responseParams;

    public function __construct($blowfishPassword,$rsParams){
        $this->len = null;
        $this->data = null;
        parent::__construct(null,$blowfishPassword,null);
        $this->responseParams = $rsParams;
    }
    protected function checkFields() {
        if (!$this->blowfishPassword) {
            throw new CmptpMissingParException("Missing blowfishPassword");
        }
	}
    public function execute(){
        $this->checkFields();

        //$respDetails = $this->decryptResponse($this->responseParams);
        $respDetails = array(); 
        $spl = explode('&', $this->responseParams);
        foreach($spl as $p){
            $paramData = explode("=",$p);
            $respDetails[$paramData[0]] = $paramData[1];
        }

        $status = ComputopUtils::getValue($respDetails, "Status");

        array_push($respDetails,parent::mapStatus($status));
        return $respDetails;
    }
}