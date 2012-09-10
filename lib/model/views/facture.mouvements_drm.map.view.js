function(doc) {
    if (doc.type != "DRM") {

        return;
    }

    if (!doc.valide.date_saisie) {

        return;
    }

    for(key in doc.mouvements) {
        var mouv = doc.mouvements[key];
    mouv.facture = 0;
    doc.region = 'tours';
        emit([mouv.facture, mouv.facturable, doc.region, doc.identifiant, doc.type, mouv.categorie, mouv.produit_hash, doc.periode, mouv.type_hash, mouv.detail_identifiant], [mouv.produit_libelle, mouv.type_libelle, mouv.volume, mouv.cvo, mouv.date, mouv.detail_libelle, 'DRM-'+doc.identifiant+'-'+doc.periode, key]);
    }
}