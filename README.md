
Projet Autoblog
===============

> This project is historically in French. See [our english README](https://github.com/mitsukarenai/Projet-Autoblog/wiki/Autoblog-Project).

Réplication automatique de contenu à partir de flux RSS/Atom, avec partage des ajouts entre les fermes d'Autoblog. 

L'objectif premier du projet Autoblog est de lutter contre la censure et toute autre forme de pression allant à l'encontre de la liberté d'expression en favorisant l'[effet Streisand](http://fr.wikipedia.org/wiki/Effet_Streisand). 

Le projet a été initialement lancé par Sébastien Sauvage : [plus d'info par ici](http://sebsauvage.net/streisand.me/fr/).

Exemples d'instances : 
- [autoblog.suumitsu.eu](http://autoblog.suumitsu.eu/)
- [streisand.hoa.ro](http://streisand.hoa.ro/)
- [ecirtam.net](https://ecirtam.net/autoblogs/)
- [autoblog.ohax.fr](http://autoblog.ohax.fr/)
- [flamby.aldarone.fr](http://flamby.aldarone.fr/)
- [tcit.fr](http://www.tcit.fr/streisand/)
- [kaelsitoo.fr](http://kaelsitoo.fr/autoblog/)
- [autoblog.postblue.info](http://autoblog.postblue.info/)


Serie 0.3 par [Mitsu](https://github.com/mitsukarenai/), [Oros](https://github.com/Oros42), [Arthur Hoaro](https://github.com/ArthurHoaro).

![logo](https://raw.github.com/mitsukarenai/Projet-Autoblog/master/resources/icon-logo.png)
Fonctionnalités majeures
===================

- Ferme d'autoblogs avec ajout facile par différents formulaires (générique, microblogging, OPML, marque-pages).
- Échange de références entre fermes avec XSAF ([Cross-Site Autoblog Farming](https://github.com/mitsukarenai/Projet-Autoblog/wiki/XSAF---Cross-Site-Autoblog-Farming)).
- Vérification du statut des sites distants, et flux de suivi des changements.
- Export des références, articles et médias.
- Contrôle de version de la ferme et alerte de mise à jour.
- Identification du type d'autoblog.
- CSS utilisateur personnalisable.
- Hébergement de documents divers (PDF, images, réplique de site web, etc.).

Branches
===================

 - [master](https://github.com/mitsukarenai/Projet-Autoblog/tree/master/) _(développement)_ : Autoblog Project serie 0.3 par Mitsu, Oros, Arthur Hoaro
 - [legacy-0.2](https://github.com/mitsukarenai/Projet-Autoblog/tree/legacy-0.2) : version VroumVroumBlog 0.2.11 par BohwaZ (VVB) & Arthur Hoaro, Mitsukarenai, Oros (index ferme d'autoblogs)
 - [legacy-0.1](https://github.com/mitsukarenai/Projet-Autoblog/tree/legacy-0.1) : version VroumVroumBlog 0.1.32 par Sebastien Sauvage
 - [legacy-0.2to0.3](https://github.com/mitsukarenai/Projet-Autoblog/tree/legacy-0.2to0.3) : script de migration 0.2 to 0.3 par Oros et Arthur Hoaro

Pré-requis techniques
=====================

- Serveur web (Apache, nginx, etc.)
- PHP 5.3 ou supérieur 
- Support SQLite 3 pour PHP

Documentation
=====================

La documentation du projet est sur le [Wiki du repo](https://github.com/mitsukarenai/Projet-Autoblog/wiki).

Accès hors ligne : `git clone https://github.com/mitsukarenai/Projet-Autoblog.wiki.git`

Licence
=====================

Domaine public.

Changelog
=====================
- 2014-02-12  MILESTONE 0.3.2
 - separate type icons
 - cache added
 - pagination fixes
 - SVG fixes
 - fix date() warnings
 - bugfixes
- 2013-10-14  MILESTONE 0.3.1
 - code semantics
 - "docs" filesize
 - robots.txt
 - bugfixes
- 2013-07-30
 - twitter2feed.php fixed (regex on class "avatar"; ```<fullname>```)
- 2013-07-22  MILESTONE 0.3
