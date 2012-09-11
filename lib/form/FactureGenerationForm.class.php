<?php

class FactureGenerationForm extends BaseForm {
    
    public function configure()
    {
        $this->setWidget('date_mouvement', new sfWidgetFormInput());
        $this->setWidget('date_facturation', new sfWidgetFormInput());
        
        $this->widgetSchema->setLabels(array(
            'date_mouvement' => 'Dernière date de prise en compte des mouvements :',
            'date_facturation' => 'Date de facturation :'
        ));
        $this->widgetSchema->setNameFormat('facture_generation[%s]');
    }     
}