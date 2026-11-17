<?php

class DocumentService{

    public static function createNumber($code){

        return $code."-".date("YmdHis");

    }

}