<?php

namespace App\Contracts;

/**
 * Contrat commun aux évènements HSE diffusables.
 *
 * Incidents, presqu'accidents et rapports environnementaux partagent l'essentiel
 * de leur forme : un auteur, une date, une gravité, un statut, un lieu. Ce qui les
 * distingue tient en deux chaînes — leur nature et, le cas échéant, leur
 * sous-type. Cette interface isole exactement cette différence.
 *
 * Conséquence : un SEUL évènement de diffusion sert les trois modules, et un
 * quatrième module n'aura qu'à implémenter ces deux méthodes pour en bénéficier.
 * C'est ce que demande le cahier des charges : ne pas dupliquer trois fois le même
 * mécanisme.
 */
interface HseEvent
{
    /**
     * Nature de l'évènement, stable dans le temps et destinée aux clients.
     *
     * Volontairement découplée du nom de la table : renommer une table ne doit pas
     * casser le contrat d'un client mobile déjà déployé, qu'on ne peut pas mettre
     * à jour d'un claquement de doigts.
     */
    public function hseKind(): string;

    /**
     * Qualification fine, quand le module en possède une.
     *
     * `Fire` ou `LTI` pour un incident, `spill` pour un rapport environnemental.
     * `null` pour un presqu'accident, qui n'a pas de sous-type.
     */
    public function hseSubtype(): ?string;
}
