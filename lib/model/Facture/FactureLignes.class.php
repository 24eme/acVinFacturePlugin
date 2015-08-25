<?php

/**
 * Model for FactureLignes
 *
 */
class FactureLignes extends BaseFactureLignes {

    public function facturerMouvements() {
        foreach ($this as $ligne) {
            $ligne->facturerMouvements();
        }
    }

    public function updateTotaux() {
        foreach ($this as $ligne) {
            $ligne->updateTotaux();
        }
    }

    public function defacturerMouvements() {
        foreach ($this as $ligne) {
            $ligne->defacturerMouvements();
        }
    }

}