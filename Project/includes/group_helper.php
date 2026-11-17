<?php

function getGroupName($studentCode)
{
    $year = substr($studentCode,0,2);

    return "CPE.".$year."231";
}