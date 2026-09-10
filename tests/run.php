<?php
require_once __DIR__ . '/../includes/class-wnm-bundle-calculator.php';
require_once __DIR__ . '/../includes/class-wnm-alert-state.php';
function expectSame($expected,$actual,$message){if($expected!==$actual){fwrite(STDERR,"FAIL: $message\nExpected: ".var_export($expected,true)."\nActual: ".var_export($actual,true)."\n");exit(1);}}
$calc=new WNM_Bundle_Calculator();
expectSame(8,$calc->possibleKits([['stock'=>20,'required'=>2],['stock'=>8,'required'=>1],['stock'=>12,'required'=>1]]),'calculates limiting component correctly');
expectSame(null,$calc->possibleKits([['stock'=>20,'required'=>2],['stock'=>null,'required'=>1]]),'returns null when stock is unmanaged');
expectSame(0,$calc->possibleKits([['stock'=>-2,'required'=>1],['stock'=>10,'required'=>1]]),'negative stock never yields negative bundle capacity');
$state=new WNM_Alert_State();
expectSame(['state'=>'low','notify'=>true],$state->transition(null,4,5),'first low state notifies');
expectSame(['state'=>'low','notify'=>false],$state->transition('low',3,5),'remaining low does not notify');
expectSame(['state'=>'normal','notify'=>false],$state->transition('low',6,5),'recovery rearms');
expectSame(['state'=>'low','notify'=>true],$state->transition('normal',4,5),'new crossing notifies');
expectSame(['state'=>'normal','notify'=>false],$state->transition(null,5,5),'equal threshold is normal');
echo "All tests passed\n";
