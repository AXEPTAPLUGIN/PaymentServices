<?php
final class PayGateway
{
    const IGFC = "IGFC";
    const CMPT1 = "CMPT1";
    const CMPT2 = "CMPT2";
    
    /**
     * Undocumented function
     *
     * @param Payment\Gateway\GatewayInterface $paymenttype
     * @return void
     */
    public static function getIstance($paymenttype)
    {
        $return =null;
        switch ($paymenttype) {
        case self::IGFC:
            $return = new \Payment\Gateway\Igfs\Gateway();
            break;
        case self::CMPT1:
            break;
        case self::CMPT2:
            break;
        default:
            break;
        }
        return $return;
    }

    

}