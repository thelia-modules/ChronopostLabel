<?php

namespace ChronopostLabel\Loop;


use ChronopostLabel\ChronopostLabel;
use ChronopostLabel\Config\ChronopostLabelConst;
use Thelia\Core\Template\Element\ArraySearchLoopInterface;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;
use Thelia\Core\Translation\Translator;

class ChronopostLabelCheckRightsLoop extends BaseLoop implements ArraySearchLoopInterface
{
    /**
     * @return ArgumentCollection
     */
    protected function getArgDefinitions()
    {
        return new ArgumentCollection();
    }

    /**
     * @return array
     */
    public function buildArray()
    {
        $ret = array();
        $config = ChronopostLabelConst::getConfig();
        if (!is_writable($config[ChronopostLabelConst::CHRONOPOST_LABEL_LABEL_DIR])) {
            $ret[] = array("ERRMES"=>Translator::getInstance()->trans("Can't write in the label directory", [], ChronopostLabel::DOMAIN_NAME), "ERRFILE"=>$config[ChronopostLabelConst::CHRONOPOST_LABEL_LABEL_DIR]);
        }
        if (!is_readable($config[ChronopostLabelConst::CHRONOPOST_LABEL_LABEL_DIR])) {
            $ret[] = array("ERRMES"=>Translator::getInstance()->trans("Can't read the label directory", [], ChronopostLabel::DOMAIN_NAME), "ERRFILE"=>$config[ChronopostLabelConst::CHRONOPOST_LABEL_LABEL_DIR]);
        }

        return $ret;
    }

    /**
     * @param LoopResult $loopResult
     * @return LoopResult
     */
    public function parseResults(LoopResult $loopResult)
    {
        foreach ($loopResult->getResultDataCollection() as $arr) {
            $loopResultRow = new LoopResultRow();
            $loopResultRow
                ->set("ERRMES", $arr["ERRMES"])
                ->set("ERRFILE", $arr["ERRFILE"]);
            $loopResult->addRow($loopResultRow);
        }

        return $loopResult;
    }

}
