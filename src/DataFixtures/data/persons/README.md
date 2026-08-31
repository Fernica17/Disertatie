# Fotografii demo pentru registrul de persoane

Douăsprezece imagini din **Labeled Faces in the Wild** (LFW), setul standard
folosit în cercetarea de recunoaștere facială, preluate prin oglinda
`logasja/lfw` de pe Hugging Face.

- Sursă: http://vis-www.cs.umass.edu/lfw/ (oglindă: https://huggingface.co/datasets/logasja/lfw)
- Citare: Huang, Ramesh, Berg, Learned-Miller — *Labeled Faces in the Wild*,
  raport tehnic 07-49, University of Massachusetts Amherst, 2007.

Sunt fotografii ale unor **persoane reale**, publicate pentru cercetare. Datele
de identificare atașate lor în `DevPersonsFixtures` sunt **inventate** și nu au
nicio legătură cu persoanele din imagini. Fixture-ul este în grupul `dev` și nu
trebuie încărcat în producție.

## Setul mare

`app:demo:fetch-faces` aduce câteva mii de portrete în `var/demo-faces/`, în
afara depozitului, iar `DevPersonsBulkFixtures` le încarcă. Acolo **numele este
cel real**, citit din numele fișierului, fiindcă arhiva îl păstrează.

Restul câmpurilor sunt generate. Acele înregistrări nu au CNP și nici serie de
act: a inventa un număr de identitate pentru o persoană reală, cu numele și
fotografia ei alături, este altceva decât a completa o fișă complet fictivă.

Fiecare imagine a fost verificată în prealabil: detectorul găsește exact o față
utilizabilă, altfel persoana ar apărea în registru fără să poată fi căutată.
