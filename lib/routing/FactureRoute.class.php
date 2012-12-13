<?php

class FactureRoute extends sfRequestRoute implements InterfaceEtablissementRoute {

	protected $facture = null;


	protected function getFactureForParameters($parameters) {

        if (preg_match('/^[1-3]{1}-[0-9]{8}-[0-9]{10}$/', $parameters['identifiant'])) {
            $identifiant = $parameters['identifiant'];
        } else {
            throw new InvalidArgumentException(sprintf('The "%s" route has an invalid parameter "%s" value "%s".', $this->pattern, 'identifiant', $parameters['identifiant']));
        }

        $facture = FactureClient::getInstance()->findByIdentifiant($identifiant);

        if (!$facture) {
            throw new sfError404Exception(sprintf('No Facture found with the id "%s".',  $parameters['identifiant']));
        }
        return $facture;
    }


    public function getFacture() {
        if (is_null($this->facture)) {
            $this->facture = $this->getFactureForParameters($this->parameters);
        }

        return $this->facture;
    }

    public function getEtablissement() {

        return $this->getFacture()->getClient();
    }
}