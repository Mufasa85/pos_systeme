-- =====================================================
-- Migration: Ajout de la colonne adresse à la table clients
-- =====================================================
-- Ce champ permet de stocker l'adresse du client pour
-- l'afficher sur la facture / ticket.
-- =====================================================

ALTER TABLE clients
ADD COLUMN adresse VARCHAR(255) ;
