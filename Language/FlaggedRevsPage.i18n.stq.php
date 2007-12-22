<?php
/** Seeltersk (Seeltersk)
 * @author Pyt
 */
$messages = array(
	'editor'                      => 'Sieuwer',
	'group-editor'                => 'Sieuwere',
	'group-editor-member'         => 'Sieuwer',
	'grouppage-editor'            => '{{ns:project}}:Sieuwer',
	'reviewer'                    => 'Wröiger',
	'group-reviewer'              => 'Wröigere',
	'group-reviewer-member'       => 'Wröiger',
	'grouppage-reviewer'          => '{{ns:project}}:Wröiger',
	'revreview-current'           => 'Äntwurf (beoarbaidboar)',
	'tooltip-ca-current'          => 'Ankiekjen fon dän aktuelle Äntwurf fon disse Siede',
	'revreview-edit'              => 'Beoarbaidje Äntwurf',
	'revreview-source'            => 'Äntwurfs-Wältext',
	'revreview-stable'            => 'Stoabil',
	'tooltip-ca-stable'           => 'Ankiekjen fon ju stoabile Version fon disse Siede',
	'revreview-oldrating'         => 'Iendeelenge bit nu:',
	'revreview-noflagged'         => 'Fon disse Siede rakt et neen markierde Versione, so dät noch neen Tjuugnis uur ju [[{{MediaWiki:Validationpage}}|Qualität]] moaked wäide kon.',
	'tooltip-ca-default'          => 'Ienstaalengen fon ju Artikkel-Qualität',
	'validationpage'              => '{{ns:help}}:Stoabile Versione',
	'revreview-quick-none'        => "'''Aktuell.''' Der wuude noch neen Version wröiged.",
	'revreview-quick-see-quality' => "'''Aktuell.''' [[{{fullurl:{{FULLPAGENAMEE}}|stable=1}} Sjuch ju lääste wröigede Version]]
	($2 [{{fullurl:{{FULLPAGENAMEE}}|oldid=$1&diff=cur&editreview=1}} {{plural:$2|Annerenge|Annerengen}}])",
	'revreview-quick-see-basic'   => "'''Aktuell.''' [[{{fullurl:{{FULLPAGENAMEE}}|stable=1}} Sjuch ju lääste wröigede Version]]
	($2 [{{fullurl:{{FULLPAGENAMEE}}|oldid=$1&diff=cur&editreview=1}} {{plural:$2|Annerenge|Annerengen}}])",
	'revreview-quick-basic'       => "'''[[{{MediaWiki:Validationpage}}|Sieuwed.]]''' [[{{fullurl:{{FULLPAGENAMEE}}|stable=0}} Sjuch ju aktuelle Version]] 
	($2 [{{fullurl:{{FULLPAGENAMEE}}|oldid=$1&diff=cur&editreview=1}} {{plural:$2|Annerenge|Annerengen}}])",
	'revreview-quick-quality'     => "'''[[{{MediaWiki:Validationpage}}|Wröiged.]]''' [[{{fullurl:{{FULLPAGENAMEE}}|stable=0}} Sjuch ju aktuelle Version]] 
	($2 [{{fullurl:{{FULLPAGENAMEE}}|oldid=$1&diff=cur&editreview=1}} {{plural:$2|Annerenge|Annerengen}}])",
	'revreview-newest-basic'      => 'Ju [{{fullurl:{{FULLPAGENAMEE}}|stable=1}} lääste wröigede Version]
	([{{fullurl:Special:Stableversions|page={{FULLPAGENAMEE}}}} sjuch aal]) wuude ap n <i>$2</i> [{{fullurl:Special:Log|type=review&page={{FULLPAGENAMEE}}}} fräiroat].
	[{{fullurl:{{FULLPAGENAMEE}}|oldid=$1&diff=cur&editreview=1}} $3 {{plural:$3|Version|Versione}}] {{plural:$3|stoant|stounde}} noch tou Wröigenge an.',
	'revreview-newest-quality'    => 'Ju [{{fullurl:{{FULLPAGENAMEE}}|stable=1}} lääste wröigede Version]
	([{{fullurl:Special:Stableversions|page={{FULLPAGENAMEE}}}} sjuch aal]) wuude ap n <i>$2</i> [{{fullurl:Special:Log|type=review&page={{FULLPAGENAMEE}}}} fräiroat].
	[{{fullurl:{{FULLPAGENAMEE}}|oldid=$1&diff=cur&editreview=1}} $3 {{plural:$3|Version|Versione}}] {{plural:$3|stoant|stounde}} noch tou Wröigenge an.',
	'revreview-basic'             => 'Dit is ju lääste [[Help:Sieuwede Versione|sieuwede]] Version,
	[{{fullurl:Special:Log|type=review&page={{FULLPAGENAMEE}}}} fräiroat] ap n <i>$2</i>. Ju [{{fullurl:{{FULLPAGENAMEE}}|stable=0}} apstuunse Version]
	kon [{{fullurl:{{FULLPAGENAMEE}}|action=edit}} beoarbaided] wäide; [{{fullurl:{{FULLPAGENAMEE}}|oldid=$1&diff=cur&editreview=1}} $3 {{plural:$3|Version|Versione}}]
{{plural:$3|stoant|stounde}} noch tou Wröigenge an.',
	'revreview-quality'           => 'Dit is ju lääste [[Help:Versionstaksoade|wröigede]] Version,
	[{{fullurl:Special:Log|type=review&page={{FULLPAGENAMEE}}}} fräiroat] ap n <i>$2</i>. Ju [{{fullurl:{{FULLPAGENAMEE}}|stable=0}} apstuunse Version]
	kon [{{fullurl:{{FULLPAGENAMEE}}|action=edit}} beoarbaided] wäide; [{{fullurl:{{FULLPAGENAMEE}}|oldid=$1&diff=cur&editreview=1}} $3 {{plural:$3|Version|Versione}}]
	{{plural:$3|stoant|stounde}} noch tou Wröigenge an.',
	'revreview-static'            => "Dit is ne [[Help:Wröigede Versione|wröigede]] Version fon '''[[:$3|$3]]''', 
	[{{fullurl:Special:Log/review|page=$1}} fräiroat] ap n <i>$2</i>.",
	'revreview-note'              => '[[{{ns:user}}:$1]] moakede ju foulgendje [[{{MediaWiki:Validationpage}}|Wröignotiz]] tou disse Version:',
	'revreview-update'            => 'Wröig älke Annerenge siet ju lääste stoabile Version (sjuch hierunner).
	Do foulgjende Foarloagen un Bielden wuden ieuwenso ferannerd:',
	'revreview-update-none'       => 'Wröig älke Annerenge siet ju lääste stoabile Version (sjuch hierunner).',
	'revreview-auto'              => '(automatisk)',
	'revreview-auto-w'            => "Du beoarbaidest ne stoabile Version, dien Beoarbaidenge wäd '''automatisk as wröiged markierd.'''
	Du schuust ju Siede deeruum foar dät Spiekerjen in ju Foarschau bekiekje.",
	'revreview-auto-w-old'        => "Du beoarbaidest ne oolde Version, dien Beoarbaidenge wäd '''automatisk as wröiged markierd.'''
Du schuust ju Siede deeruum foar dät Spiekerjen in ju Foarschau bekiekje.",
	'hist-stable'                 => '[sieuwed]',
	'hist-quality'                => '[wröiged]',
	'flaggedrevs'                 => 'Markierde Versione',
	'review-logpage'              => 'Artikkel-Wröig-Logbouk',
	'review-logpagetext'          => 'Dit is dät Annerengs-Logbouk fon do [[{{MediaWiki:Validationpage}}|Sieden-Fräigoawen]].',
	'review-logentry-app'         => 'wröigede [[$1]]',
	'review-logentry-dis'         => 'fersmeet ne Version fon [[$1]]',
	'review-logaction'            => 'Version-ID $1',
	'stable-logpage'              => 'Stoabile-Versione-Logbouk',
	'stable-logpagetext'          => 'Dit is dät Annerengs-Logbouk fon do Konfigurationsienstaalengen fon do [[{{MediaWiki:Validationpage}}|Stoabile Versione]]',
	'stable-logentry'             => 'konfigurierde ju Sieden-Ienstaalenge fon [[$1]]',
	'stable-logentry2'            => 'sätte ju Sieden-Ienstaalenge foar [[$1]] tourääch',
	'revisionreview'              => 'Versionswröigenge',
	'revreview-main'              => 'Du moast ne Artikkelversion tou Wröigenge uutwääle.

Sjuch [[{{ns:special}}:Unreviewedpages]] foar ne Lieste fon nit pröiwede Versione.',
	'revreview-selected'          => "Wäälde Versione fon '''$1:'''",
	'revreview-text'              => 'Ne stoabile Version wäd bie ju Siedendeerstaalenge ljauer nuumen as ne näiere Version.',
	'revreview-toolow'            => 'Du moast foar älk fon do unnerstoundende Attribute n Wäid haager as „{{int:revreview-accuracy-0}}“ ienstaale, 
deermäd ne Version as wröiged jält. Uum ne Version tou fersmieten, mouten aal Attribute ap „{{int:revreview-accuracy-0}}“ stounde.',
	'revreview-flag'              => 'Wröig Version (#$1)',
	'revreview-legend'            => 'Inhoold fon ju Version wäidierje',
	'revreview-notes'             => 'Antouwiesende Bemäärkengen un Notizen:',
	'revreview-accuracy'          => 'Akroategaid',
	'revreview-accuracy-0'        => 'nit fräiroat',
	'revreview-accuracy-1'        => 'sieuwed',
	'revreview-accuracy-2'        => 'wröiged',
	'revreview-accuracy-3'        => 'Wällen wröiged',
	'revreview-accuracy-4'        => 'exzellent',
	'revreview-depth'             => 'Djüpte',
	'revreview-depth-0'           => 'nit fräiroat',
	'revreview-depth-1'           => 'eenfach',
	'revreview-depth-2'           => 'middel',
	'revreview-depth-3'           => 'hooch',
	'revreview-depth-4'           => 'exzellent',
	'revreview-style'             => 'Leesboarhaid',
	'revreview-style-0'           => 'nit fräiroat',
	'revreview-style-1'           => 'akzeptoabel',
	'revreview-style-2'           => 'goud',
	'revreview-style-3'           => 'akroat',
	'revreview-style-4'           => 'exzellent',
	'revreview-log'               => 'Logbouk-Iendraach:',
	'revreview-submit'            => 'Wröigenge spiekerje',
	'revreview-changed'           => "'''Ju Aktion kuude nit ap disse Version anwoand wäide.'''

Ne Foarloage of ne Bielde wuuden sunner spezifiske Versionsnummer anfoarderd. Dit kon passierje, wan ne dynamiske Foarloage ne wiedere Foarloage of ne Bielde änthaalt, ju der von ne Variable ouhongich is, ju sik siet Ounfang fon ju Pröiwenge annerd häd. Fonnäien Leeden fon ju Siede un Startjen fon ju Wröigenge kon dät Problem ouhälpe.",
	'stableversions'              => 'Stoabile Versione',
	'stableversions-leg1'         => 'Lieste fon do wröigede Versione foar n Artikkel',
	'stableversions-page'         => 'Artikkelnoome:',
	'stableversions-none'         => '„[[:$1]]“ häd neen wröigede Versione.',
	'stableversions-list'         => 'Dit is ju Lieste fon do wröigede Versione fon „[[:$1]]“:',
	'stableversions-review'       => 'wröiged ap n <i>$1</i> truch $2',
	'review-diff2stable'          => 'Unnerscheed tou ju stoabile Version',
	'unreviewedpages'             => 'Nit wröigede Artikkele',
	'viewunreviewed'              => 'Lieste fon nit wröigede Artikkele',
	'unreviewed-outdated'         => 'Wies bloot Sieden, do der nit wröigede Versione ätter ne stoabile Verison hääbe.',
	'unreviewed-category'         => 'Kategorie:',
	'unreviewed-diff'             => 'Annerengen',
	'unreviewed-list'             => 'Disse Siede wiest Artikkele, do der noch sieläärge nit wröiged wuuden of nit wröigede Versione hääbe.',
	'revreview-visibility'        => 'Disse Siede häd ne [[{{MediaWiki:Validationpage}}|stoabile Version]], ju der
	[{{fullurl:Special:Stabilization|page={{FULLPAGENAMEE}}}} konfigurierd] wäide kon.',
);
