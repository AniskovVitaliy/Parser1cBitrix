<?php
function pr($var, $die = false, $all = false)
{
    global $USER;
    if (($USER->GetID() == 1) || ($all == true)) {
        $bt = debug_backtrace();
        $bt = $bt[0];?>
        <div style='font-size:9pt; color:#000; background:#fff; border:1px dashed #000;'>
            <div style='padding:3px 5px; background:#99CCFF; font-weight:bold;'>File: <?= $bt["file"] ?>
                [<?= $bt["line"] ?>]
            </div>
            <?if ($var === 0) {
                echo '<pre>пусто</pre>';
                var_dump($var);
            } else {
                echo '<pre>';
                print_r($var);
                echo '</pre>';
            }?>
        </div>
        <?if ($die) {
            die();
        }
    }
}