<?php

namespace Command;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Formatter\OutputFormatterStyle;
 
/**
 * CheckCommand check wheter ID is ready or not.
 * 
 * @uses Command
 * @package GliwiceDowod
 * @author Marcin Dryka <marcin@dryka.pl> 
 */
class CheckCommand extends Command 
{
    const SERVICE = 'http://www.um.gliwice.pl/bip/index.php?id=16853%2F1&s=1';
    const STATUS_GIVEN = "Dowód został już wydany";
    const STATUS_WRONG_PESEL = "Błędnie podany numer PESEL";
    const STATUS_UNKNOWN = "Nieznany status";

    /**
     * configure 
     * 
     * @access protected
     * @return void
     */
    protected function configure() 
    {
        $this
        ->setName('id:check')
        ->setDescription('Sprawdzenie, dla mieszkańców Gliwic, czy zamówiony przez nich dowód został już wyprodukowany')
        ->setDefinition(array(
            new InputArgument('PESEL', InputArgument::REQUIRED, 'PESEL numer pesel')
        ));
    }
 
    /**
     * execute task content
     * 
     * @param InputInterface $input 
     * @param OutputInterface $output 
     * @access protected
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output) 
    {
        $style = new OutputFormatterStyle('red', null, array('bold'));
        $output->getFormatter()->setStyle('pending', $style);

        $pesel = $input->getArgument('PESEL');
        if ($input->getOption('verbose')) {
            $output->writeln(sprintf("<comment>Sprawdzam status dowodu dla osoby z numerem PESEL:</comment> <info>%s</info>", $pesel));
        }

        try
        {
            if ($this->ask($pesel)) {
                $output->writeln("<info>Twój dowód jest gotowy do odbioru!</info>");
            } else {
                $output->writeln("<pending>Dowód jest w trakcie produkcji</pending>");
            }
        } catch (\Exception\IdStatusException $e) {
            $output->writeln($e->getMessage());
        }
    }

    /**
     * ask server wheter ID is ready or not
     * 
     * @param integer $pesel 
     * @access protected
     * @return bool
     */
    protected function ask($pesel)
    {
        $status = $this->getStatus($pesel);
        switch ($status) {
            case "DOWÓD WYDANY":
                throw new \Exception\IdStatusException(self::STATUS_GIVEN);
            case "Błędnie wpisany numer PESEL":
                throw new \Exception\IdStatusException(self::STATUS_WRONG_PESEL);
            case "DOWÓD W TRAKCIE PRODUKCJI":
                return false;
            case "DOWÓD GOTOWY DO ODBIORU":
                return true;
            default:
               throw new \Exception\IdStatusException(self::STATUS_UNKNOWN); 
        }
    }

    /**
     * getStatus get remote status
     * 
     * @param integer $pesel 
     * @access protected
     * @return string status
     */
    protected function getStatus($pesel)
    {
        $response = $this->doRequest($pesel);
        $result = $this->parseResponse($response);
        return $result;
    }

    /**
     * doRequest do http call to check status
     * 
     * @param integer $pesel 
     * @access protected
     * @return string HTML response
     */
    protected function doRequest($pesel)
    {
        $url = sprintf("%s&p=%s", self::SERVICE, $pesel);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }

    /**
     * parseResponse parse response content
     * 
     * @param string $response 
     * @access protected
     * @return string status text
     */
    protected function parseResponse($response)
    {
        $dom = new \DomDocument();
        $dom->strictErrorChecking = false;
        @$dom->loadHtml($response);
        $finder = new \DomXPath($dom);
        $classname="tytulNewsa";
        $nodes = $finder->query("//*[contains(@class, '$classname')]");
        return $nodes->item(0)->textContent;
    }
}
