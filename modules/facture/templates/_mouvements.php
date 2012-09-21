<?php 
use_helper('Float');
use_helper('Date');
use_helper('Prix'); 
?>
<fieldset id="mouvement_drm">
    <legend>Mouvements en attente de facturation</legend>
    <table class="table_recap">
        <thead>
            <tr>
                <th style="width: 90px;">Date</th>
                <th style="width: 180px;">Document</th>
                <th style="width: 180px;">Produits</th>
                <th>Type</th>
                <th>Prix TTC</th>
            </tr>
        </thead>
        <tbody>
        <?php $i = 1; ?>
        <?php foreach($mouvements as $mouvement): ?>
        <?php $i++; ?>
            <tr <?php if($i%2!=0) echo ($mouvement->volume>0)? ' class="alt"' : 'class="alt"';  ?>>
                <td><?php echo format_date($mouvement->date,'dd/MM/yyyy'); ?></td>
                <td><?php 
                $numeroFormatted = (strstr($mouvement->numero, 'DRM')!== false)? DRMClient::getInstance()->getLibelleFromIdDRM($mouvement->numero) :
                SV12Client::getInstance()->getLibelleFromIdSV12($mouvement->numero);
                
                echo $numeroFormatted; ?></td>
                <td><?php echo $mouvement->produit_libelle ?> </td>
                <td><?php echo $mouvement->type_libelle.' '.$mouvement->detail_libelle ?></td>
                <td <?php echo ($mouvement->volume>0)? ' class="positif"' : 'class="negatif"';?> >
                    <?php echoTtc($mouvement->prix_ht); ?>&nbsp;&euro;
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</fieldset>