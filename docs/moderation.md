# Signalements et modération

Demande du développeur (23/11/2026) : avant d'ouvrir le réseau social au public, pouvoir signaler
un contenu ou un compte, et le traiter. Le règlement européen sur les services numériques (DSA)
impose aux hébergeurs un mécanisme de signalement, une décision motivée communiquée à l'auteur et
à qui a signalé, et une voie de recours : **à faire vérifier par un juriste** avant l'ouverture.

## Signaler (`POST /social/reports`)

`type` (`profile`, `review`, `list`, `comment`, `message`), `id` (l'`id` du profil, le `review_id`,
l'id de la liste, du commentaire ou du message), `reason` (`spam`, `harassment`, `hate`, `sexual`,
`violence`, `spoiler`, `illegal`, `other` ; libellés : `GET /social/report-reasons`), `details`
facultatif (1 000 caractères). Seulement ce que le profil voit (404 sinon : un message, s'il est
membre de la conversation), jamais son propre contenu (422) ; signaler de nouveau la même chose
met à jour le signalement ouvert. 30 par heure (`throttle:reports`). Table `reports`
(`App\Services\Social\Reports`), supprimée avec le profil qui signale ; l'auteur signalé est gardé
(`author_profile_id`, mis à null s'il est supprimé). Un e-mail part à `VIXEO_CONTACT_EMAIL` après la
réponse (sans lui, rien ; la commande ci-dessous suffit).

## Traiter

```bash
php artisan vixeo:reports            # les signalements ouverts (--all : aussi les traités)
php artisan vixeo:moderate 12 hide --reason="Propos insultants"
php artisan vixeo:moderate 12 dismiss --reason="Aucune règle enfreinte"
php artisan vixeo:moderate 12 restore --reason="Rétabli après réclamation"
php artisan vixeo:moderate 12 photo --reason="Photo inappropriée"
```

- `hide` : un avis, une liste ou un commentaire est **masqué** (`hidden_at` : plus montré à
  personne, ni dans le fil, ni sur les fiches, ni sur les pages) ; un message est vidé comme s'il
  avait été supprimé (texte, carte partagée, fichiers) ; un profil est **suspendu**
  (`profiles.suspended_at` : plus de page, de recherche, de contenu montré, et il ne peut plus rien
  publier). Tous les signalements ouverts du même contenu sont fermés.
- `dismiss` : le signalement est rejeté, le contenu reste.
- `restore` : ce qui avait été masqué (ou suspendu) revient (recours de l'auteur).
- `photo` : un profil signalé perd **sa photo** (fichier supprimé, l'avatar revient) sans être
  suspendu ; ses signalements ouverts sont fermés (« Votre photo de profil a été retirée par la
  modération : … »).

La raison (`--reason`, obligatoire) est communiquée par une notification `moderation` de la page
« Notifications » (et en push) : à l'auteur (« Votre avis sur Dune a été masqué par la modération :
… ») et à chaque profil qui avait signalé (« Merci pour votre signalement : le contenu a été
retiré. » ou la raison du rejet).

## Bloquer et masquer

Voir `docs/social.md` (« Bloquer, masquer ») : bloquer coupe tout lien entre les deux profils,
masquer retire seulement un compte suivi du fil.
