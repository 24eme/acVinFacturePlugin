<?php
class FactureClient extends acCouchdbClient {

    const FACTURE_LIGNE_ORIGINE_TYPE_DRM = "DRM";
    const FACTURE_LIGNE_ORIGINE_TYPE_SV = "SV12";
    const FACTURE_LIGNE_MOUVEMENT_TYPE_PROPRIETE = "propriete";
    const FACTURE_LIGNE_MOUVEMENT_TYPE_CONTRAT = "contrat";
    const FACTURE_LIGNE_PRODUIT_TYPE_VINS = "contrat_vins";
    const FACTURE_LIGNE_PRODUIT_TYPE_MOUTS = "contrat_mouts";
    const FACTURE_LIGNE_PRODUIT_TYPE_RAISINS = "contrat_raisins";
    const FACTURE_LIGNE_PRODUIT_TYPE_ECART = "ecart";
    
    const STATUT_REDRESSEE = 'redressee';
    const STATUT_NONREDRESSABLE = 'non redressable';

    public static function getInstance() {
        return acCouchdbManager::getClient("Facture");
    }

    public function getId($prefix,$identifiant, $numeroFacture) {
        return 'FACTURE-'.$prefix.'-'.$identifiant.'-'.$numeroFacture;
    }


    public function getNextNoFacture($prefix,$idClient,$date)
    {   
        $id = '';
    	$facture = self::getAtDate($prefix,$idClient,$date, acCouchdbClient::HYDRATE_ON_DEMAND)->getIds();
        if (count($facture) > 0) {
            $id .= ((double)str_replace('FACTURE-'.$prefix.'-'.$idClient.'-', '', max($facture)) + 1);
        } else {
            $id.= $date.'01';
        }
        return $id;
    }
    
    public function getAtDate($prefix,$idClient,$date, $hydrate = acCouchdbClient::HYDRATE_DOCUMENT) {
        return $this->startkey('FACTURE-'.$prefix.'-'.$idClient.'-'.$date.'00')->endkey('FACTURE-'.$prefix.'-'.$idClient.'-'.$date.'99')->execute($hydrate);        
    }

    public function getFacturationForSociete($societe, $level) {
        return MouvementfactureFacturationView::getInstance()->getMouvementsBySocieteWithReduce($societe, 0, 1, $level);
    }

    public function createDoc($mvts, $societe, $emmetteur = null, $date_facturation = null) {

        $facture = new Facture();
        $facture->storeDatesCampagne($date_facturation,'2011-2012');        
        $facture->constructIds($societe);        
        $facture->storeEmetteur();
        $facture->storeDeclarant();
        $facture->storeLignes($mvts,$societe->famille);        
        $facture->storePapillons();        
        $facture->updateTotaux();        
        $facture->storeOrigines();    
        return $facture;
    }  
    
    public function findByIdentifiant($identifiant) {
        return $this->find('FACTURE-' . $identifiant);
    }

    public function findByPrefixAndSocieteAndId($prefix,$idSociete, $idFacture) {
        return $this->find('FACTURE-'.$prefix.'-'. $idSociete . '-' . $idFacture);
    }

    public function getMouvementsForMasse($regions,$level) {
        if(!$regions){
            return MouvementfactureFacturationView::getInstance()->getMouvements(0, 1,$level);
        }
        $mouvementsByRegions = array();
        foreach ($regions as $region) {
            $mouvementsByRegions = array_merge(MouvementfactureFacturationView::getInstance()->getMouvementsFacturablesByRegions(0, 1,$region,$level),$mouvementsByRegions);
        }
        return $mouvementsByRegions;    
    }
    
    public function getMouvementsNonFacturesByEtb($mouvements) {

        $generationFactures = array();
        foreach ($mouvements as $mouvement) {
            if (array_key_exists($mouvement->key[MouvementfactureFacturationView::KEYS_ETB_ID], $generationFactures)) {
                $generationFactures[$mouvement->key[MouvementfactureFacturationView::KEYS_ETB_ID]][] = $mouvement;
            } else {
                $generationFactures[$mouvement->key[MouvementfactureFacturationView::KEYS_ETB_ID]] = array();
                $generationFactures[$mouvement->key[MouvementfactureFacturationView::KEYS_ETB_ID]][] = $mouvement;
            }
        }
        return $generationFactures;
    }
    
    public function filterWithParameters($mouvementsByEtb, $parameters) {
        
    if (isset($parameters['date_mouvement']) && ($parameters['date_mouvement'] != '')){
        $date_mouvement = Date::getIsoDateFromFrenchDate($parameters['date_mouvement']);
        foreach ($mouvementsByEtb as $identifiant => $mouvements) {
            foreach ($mouvements as $key => $mouvement) {
                    if(Date::supEqual($mouvement->value[MouvementfactureFacturationView::VALUE_DATE],$date_mouvement)) {
                        unset($mouvements[$key]);
                        $mouvementsByEtb[$identifiant] = $mouvements;
                    }
            }
        }
    }
    if (isset($parameters['seuil']) && $parameters['seuil'] != '') {
        foreach ($mouvementsByEtb as $identifiant => $mouvements) {
            $somme = 0;
            foreach ($mouvements as $mouvement) {
                $somme+= $mouvement->value[MouvementfactureFacturationView::VALUE_VOLUME] * $mouvement->value[MouvementfactureFacturationView::VALUE_CVO];
            }
            $somme = abs($somme);
            $somme = $this->ttc($somme);
            if ($somme >= $parameters['seuil']) {
                    unset($mouvementsByEtb[$identifiant]);
                }           
        }
    }
    $mouvementsByEtb = $this->cleanMouvementsByEtb($mouvementsByEtb);
    

    return $mouvementsByEtb;
    }

    private function cleanMouvementsByEtb($mouvementsByEtb){
        if (count($mouvementsByEtb) == 0)
        return null;
        $nb_mouvements = 0;
        foreach ($mouvementsByEtb as $identifiant => $mouvement) {
            $nb_mouvements+= count($mouvement);
            if($nb_mouvements > 0) return $mouvementsByEtb;
            }
        if($nb_mouvements==0) return null;
    }


    public function createFacturesBySoc($generationFactures,$date_facturation) {
        
        $generation = new Generation();
        $generation->date_emission = date('Y-m-d-H:i');
        $generation->type_document = GenerationClient::TYPE_DOCUMENT_FACTURES;
        $generation->documents = array();
        $generation->somme = 0;
        $cpt = 0;

        foreach ($generationFactures as $societeID => $mouvementsSoc) {
            $societe = SocieteClient::getInstance()->find($societeID);
            $f = $this->createDoc($mouvementsSoc, $societe, $date_facturation);
            
            $f->save();

            $generation->somme += $f->total_ttc;
            $generation->add('documents')->add($cpt, $f->_id);
            $cpt++;
        }

        return $generation;
    }

    private function ttc($p) {
        return $p + $p * 0.196;
    }

    public function getTypes() {
        return array(FactureClient::FACTURE_LIGNE_PRODUIT_TYPE_VINS,
            FactureClient::FACTURE_LIGNE_PRODUIT_TYPE_RAISINS,
            FactureClient::FACTURE_LIGNE_PRODUIT_TYPE_MOUTS);
    }

    public function getProduitsFromTypeLignes($lignes) {
        $produits = array();
        foreach ($lignes as $ligne) {
            if (array_key_exists($ligne->produit_hash, $produits)) {
                $produits[$ligne->produit_hash][] = $ligne;
            } else {
                $produits[$ligne->produit_hash] = array();
                $produits[$ligne->produit_hash][] = $ligne;
            }
        }
        return $produits;
    }

    public function isRedressee($factureview){
      return ($factureview->value[FactureSocieteView::VALUE_STATUT] == self::STATUT_REDRESSEE);
    }
        
    public function isRedressable($factureview){
      return !$this->isRedressee($factureview) && $factureview->value[FactureSocieteView::VALUE_STATUT] != self::STATUT_NONREDRESSABLE;
    }
        
    public function getTypeLignePdfLibelle($typeLibelle) {
      if ($typeLibelle == self::FACTURE_LIGNE_MOUVEMENT_TYPE_PROPRIETE)
	return 'propriété';
      switch ($typeLibelle) {
      case self::FACTURE_LIGNE_PRODUIT_TYPE_MOUTS:
	return 'contrats moûts';
	
      case self::FACTURE_LIGNE_PRODUIT_TYPE_RAISINS:
	return 'contrats raisins';
	
      case self::FACTURE_LIGNE_PRODUIT_TYPE_VINS:
	return 'contrats vins';
      }
      return '';
    }

    public function defactureCreateAvoirAndSaveThem(Facture $f) {
      if (!$f->isRedressable()) {
	return ;
      }
      $avoir = clone $f;
      foreach($avoir->lignes as $type => $lignes) {
	foreach($lignes as $id => $ligne) {
	  $ligne->volume *= -1;
	  $ligne->montant_ht *= -1;
	}
      }
      $avoir->total_ttc *= -1;
      $avoir->total_ht *= -1;
      $avoir->remove('echeances');
      $avoir->add('echeances');
      $soc = SocieteClient::getInstance()->find($avoir->identifiant);
      $avoir->constructIds($soc, $f->region);
      $avoir->statut = self::STATUT_NONREDRESSABLE;
      $avoir->save();
      $f->defacturer();
      $f->save();
      return $avoir;
    }
    
}
