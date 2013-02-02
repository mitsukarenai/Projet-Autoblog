EXPERIMENTAL !!

Don't run this, or kittens will die !
Ne pas utiliser sinon des chatons vont morfler !

VroumVroumBlog 0.3 (build 2013-02-02 #0) changelog (+ajouté -supprimé *modifié //commentaire)
- local.db
- download_media_from   (toutes sources acceptées)

* la recherche se fait en LIKE et n'est pas indexée FTS3 -> susceptible de changer
* code HTML général passé en HTML 5
* update_log table migrée sur articles.db
* modification notable du CSS pour conformer à l'index Ferme Autoblog
* download_media_type (hardcodé et on y fourgue tout ce qui peut être "src" communément: images, docs, audio, vidéo) -> susceptible de changer, méthode flemme à comprendre la regexp

+ CSS adapté selon SITE_TYPE
+ paramètre SITE_TYPE dans VVB.ini  (valeurs possibles:  'generic', 'microblog' et 'shaarli')

// la mécanique interne n'a que peu changé et pourrait être optimisée, la structure des tables peut être révisée (surtout si on veut revenir à une recherche MATCH, donc table suppl avec indexation et FTS3 ou FTS4 blablabla)
// le CSS type 'generic' semble ok, type 'microblog' est bien avancé (à moins que OSEF de l'emplacement du champ de recherche), 'shaarli' n'est pas débuté
// affichage, pagination et recherche semblent opérationnels, tests de performances et fiabilité à faire
// VVB 0.2 reposait sur .htaccess pour les URL jolies, ce qui limite à Apache, donc maintenant c'est juste "./?" dans les URL des titres pour tous (et PAF, ça transforme les 404 en 200 !)

