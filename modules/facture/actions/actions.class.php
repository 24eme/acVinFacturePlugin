<?php
class factureActions extends sfActions {
    
  public function executeIndex(sfWebRequest $request) {
    
       $this->form = new EtablissementChoiceForm();
       //trouver la facture dans bas.
       //deplacer $this->srcPdf dans _generateTex.php
       //ecrire le fichier tex.
       
    }
        

    public function executeLatex(sfWebRequest $request) {
        
        $this->setLayout(false);
        
        $this->facture = FactureClient::getInstance()->findByEtablissementAndId($this->getRoute()->getEtablissement()->identifiant, $request->getParameter('factureid'));
        
        $this->lignesPropriete = $this->getFactureLignesByMouvementType($this->facture,'propriete');
        $this->lignesContrat = $this->getFactureLignesByMouvementType($this->facture,'contrat');
        
        $this->srcPdf = $this->getPartial('generateTex',array('facture' => $this->facture,
                                                              'lignesPropriete' => $this->lignesPropriete,
                                                              'lignesContrat' => $this->lignesContrat));

        $this->srcTexFilename = $this->facture->identifiant.'-'.count($this->facture->lignes);
        $this->extTex = 'tex';
        $this->statut = $this->creerFichier('/data',$this->srcTexFilename, $this->extTex,  $this->srcPdf);
        
        unlink("/tmp/".$this->srcTexFilename."*.pdf");
        
        $cmdCompileLatex = '/usr/bin/pdflatex -output-directory=/tmp/ -synctex=1 -interaction=nonstopmode data/'.$this->srcTexFilename.'.'.$this->extTex.' 2> /dev/null ; chown www-data '.$this->srcTexFilename.'.pdf';

        $output = exec($cmdCompileLatex);
        $pdfFile = $this->srcTexFilename.".pdf";
        //print $output;
        
      //  $this->forward404Unless($this->);
        $attachement = "attachment; filename=".$pdfFile;
        header("content-type: application/pdf\n");
        //header("content-length: ".filesize($pdfFile)."\n");
        header("content-disposition: $attachement\n\n");
        echo file_get_contents("/tmp/".$pdfFile);
        unlink("/tmp/".$this->srcTexFilename.".aux");
        unlink("/tmp/".$this->srcTexFilename.".log");
        exit;
    }
    
    private function creerFichier($fichierChemin, $fichierNom, $fichierExtension, $fichierContenu, $droit=""){
        $fichierCheminComplet = $_SERVER["DOCUMENT_ROOT"].$fichierChemin."/".$fichierNom;
        if($fichierExtension!=""){
        $fichierCheminComplet = $fichierCheminComplet.".".$fichierExtension;
        }

        // création du fichier sur le serveur
        $leFichier = fopen($fichierCheminComplet, "w");
        fwrite($leFichier, html_entity_decode(htmlspecialchars_decode($fichierContenu),HTML_ENTITIES));
        fclose($leFichier);

        // la permission
        if($droit==""){
        $droit="0600";
        }

        // on vérifie que le fichier a bien été créé
        $t_infoCreation['fichierCreer'] = false;
        if(file_exists($fichierCheminComplet)==true){
        $t_infoCreation['fichierCreer'] = true;
        }

        // on applique les permission au fichier créé
        $retour = chmod($fichierCheminComplet,intval($droit,8));
        $t_infoCreation['permissionAppliquer'] = $retour;

        return $t_infoCreation;
    }

    static function triOriginDate($ligne_0, $ligne_1)
    {
        if ($ligne_0->origine_date == $ligne_1->origine_date) {

        return 0;
        }
        return ($ligne_0->origine_date > $ligne_1->origine_date) ? -1 : +1;
    }
    
    private function getFactureLignesByMouvementType($facture,$mouvement_type)
    {
        $lignesByMouvementType = array();
        foreach ($facture->lignes as $ligne) 
        {
            if($ligne->mouvement_type == $mouvement_type)
            {
                $lignesByMouvementType[] = $ligne;
            }  
        }
        usort($lignesByMouvementType, array("factureActions", "triOriginDate"));
        return $lignesByMouvementType;
    }
    
    
}