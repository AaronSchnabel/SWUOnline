<?php

  include "CardSetters.php";
  include "CardGetters.php";

function EvaluateCombatChain(&$totalAttack, &$totalDefense, &$attackModifiers=[])
{
  global $combatChain, $mainPlayer, $currentTurnEffects, $defCharacter, $playerID, $combatChainState, $CCS_LinkBaseAttack;
  global $CCS_WeaponIndex, $mainCharacter, $mainAuras;
    UpdateGameState($playerID);
    BuildMainPlayerGameState();
    $attackType = CardType($combatChain[0]);
    $canGainAttack = CanGainAttack();
    $snagActive = SearchCurrentTurnEffects("CRU182", $mainPlayer) && $attackType == "AA";
    for($i=1; $i<count($combatChain); $i+=CombatChainPieces())
    {
      $from = $combatChain[$i+1];
      $resourcesPaid = $combatChain[$i+2];

      if($combatChain[$i] == $mainPlayer)
      {
        if($i == 1) $attack = $combatChainState[$CCS_LinkBaseAttack];
        else $attack = AttackValue($combatChain[$i-1]);
        if($canGainAttack || $i == 1 || $attack < 0)
        {
          array_push($attackModifiers, $combatChain[$i-1]);
          array_push($attackModifiers, $attack);
          if($i == 1) $totalAttack += $attack;
          else AddAttack($totalAttack, $attack);
        }
        $attack = AttackModifier($combatChain[$i-1], $combatChain[$i+1], $combatChain[$i+2], $combatChain[$i+3]) + $combatChain[$i + 4];
        if(($canGainAttack && !$snagActive) || $attack < 0)
        {
          array_push($attackModifiers, $combatChain[$i-1]);
          array_push($attackModifiers, $attack);
          AddAttack($totalAttack, $attack);
        }
      }
      else
      {
        $totalDefense += BlockingCardDefense($i-1, $combatChain[$i+1], $combatChain[$i+2]);
      }
    }

    /*
    //Now check current turn effects
    for($i=0; $i<count($currentTurnEffects); $i+=CurrentTurnPieces())
    {
      if(IsCombatEffectActive($currentTurnEffects[$i]) && !IsCombatEffectLimited($i))
      {
        if($currentTurnEffects[$i+1] == $mainPlayer)
        {
          $attack = EffectAttackModifier($currentTurnEffects[$i]);
          if(($canGainAttack || $attack < 0) && !($snagActive && $currentTurnEffects[$i] == $combatChain[0]))
          {
            array_push($attackModifiers, $currentTurnEffects[$i]);
            array_push($attackModifiers, $attack);
            AddAttack($totalAttack, $attack);
          }
        }
      }
    }
    */

    if($combatChainState[$CCS_WeaponIndex] != -1)
    {
      $attack = 0;
      if($attackType == "W") $attack = $mainCharacter[$combatChainState[$CCS_WeaponIndex]+3];
      else if(DelimStringContains(CardSubtype($combatChain[0]), "Aura")) $attack = $mainAuras[$combatChainState[$CCS_WeaponIndex]+3];
      else if(IsAlly($combatChain[0]))
      {
        $allies = &GetAllies($mainPlayer);
        $attack = $allies[$combatChainState[$CCS_WeaponIndex]+7];
      }
      if($canGainAttack || $attack < 0)
      {
        array_push($attackModifiers, "+1 Attack Counters");
        array_push($attackModifiers, $attack);
        AddAttack($totalAttack, $attack);
      }
    }
    $attack = MainCharacterAttackModifiers();
    if($canGainAttack || $attack < 0)
    {
      array_push($attackModifiers, "Character/Equipment");
      array_push($attackModifiers, $attack);
      AddAttack($totalAttack, $attack);
    }
    $attack = AuraAttackModifiers(0);
    if($canGainAttack || $attack < 0)
    {
      array_push($attackModifiers, "Aura Ability");
      array_push($attackModifiers, $attack);
      AddAttack($totalAttack, $attack);
    }
    $attack = ArsenalAttackModifier();
    if($canGainAttack || $attack < 0)
    {
      array_push($attackModifiers, "Arsenal Ability");
      array_push($attackModifiers, $attack);
      AddAttack($totalAttack, $attack);
    }
}

function CharacterLevel($player)
{
  global $CS_CachedCharacterLevel;
  return GetClassState($player, $CS_CachedCharacterLevel);
}

function AddAttack(&$totalAttack, $amount)
{
  global $combatChain;
  if($amount > 0 && $combatChain[0] == "OUT100") $amount += 1;
  if($amount > 0 && ($combatChain[0] == "OUT065" || $combatChain[0] == "OUT066" || $combatChain[0] == "OUT067") && ComboActive()) $amount += 1;
  if($amount > 0) $amount += PermanentAddAttackAbilities();
  $totalAttack += $amount;
}

function BlockingCardDefense($index, $from="", $resourcesPaid=-1)
{
  global $combatChain, $defPlayer, $mainPlayer, $currentTurnEffects;
  $from = $combatChain[$index+2];
  $resourcesPaid = $combatChain[$index+3];
  $defense = BlockValue($combatChain[$index]) + BlockModifier($combatChain[$index], $from, $resourcesPaid) + $combatChain[$index + 6];
  if(CardType($combatChain[$index]) == "E")
  {
    $defCharacter = &GetPlayerCharacter($defPlayer);
    $charIndex = FindDefCharacter($combatChain[$index]);
    $defense += $defCharacter[$charIndex+4];
  }
  for ($i = 0; $i < count($currentTurnEffects); $i += CurrentTurnPieces()) {
    if (IsCombatEffectActive($currentTurnEffects[$i]) && !IsCombatEffectLimited($i)) {
      if ($currentTurnEffects[$i + 1] == $defPlayer) {
        $defense += EffectBlockModifier($currentTurnEffects[$i], index:$index);
      }
    }
  }
  if($defense < 0) $defense = 0;
  return $defense;
}

function AddCombatChain($cardID, $player, $from, $resourcesPaid)
{
  global $combatChain, $turn;
  $index = count($combatChain);
  array_push($combatChain, $cardID);
  array_push($combatChain, $player);
  array_push($combatChain, $from);
  array_push($combatChain, $resourcesPaid);
  array_push($combatChain, RepriseActive());
  array_push($combatChain, 0);//Attack modifier
  array_push($combatChain, 0);//Defense modifier
  if($turn[0] == "B" || CardType($cardID) == "DR") OnBlockEffects($index, $from);
  CurrentEffectAttackAbility();
  return $index;
}

function CombatChainPowerModifier($index, $amount)
{
  global $combatChain;
  $combatChain[$index+5] += $amount;
  ProcessPhantasmOnBlock($index);
}

function CacheCombatResult()
{
  global $combatChain, $combatChainState, $CCS_CachedTotalAttack, $CCS_CachedTotalBlock, $CCS_CachedDominateActive, $CCS_CachedNumBlockedFromHand, $CCS_CachedOverpowerActive;
  global $CSS_CachedNumActionBlocked, $CCS_CachedNumDefendedFromHand;
  if(count($combatChain) > 0)
  {
    $combatChainState[$CCS_CachedTotalAttack] = 0;
    $combatChainState[$CCS_CachedTotalBlock] = 0;
    EvaluateCombatChain($combatChainState[$CCS_CachedTotalAttack], $combatChainState[$CCS_CachedTotalBlock]);
    $combatChainState[$CCS_CachedDominateActive] = (IsDominateActive() ? "1" : "0");
    if ($combatChainState[$CCS_CachedNumBlockedFromHand] == 0) $combatChainState[$CCS_CachedNumBlockedFromHand] = NumBlockedFromHand();
    $combatChainState[$CCS_CachedOverpowerActive] = (IsOverpowerActive() ? "1" : "0");
    $combatChainState[$CSS_CachedNumActionBlocked] = NumActionBlocked();
    $combatChainState[$CCS_CachedNumDefendedFromHand] = NumDefendedFromHand(); //Reprise
  }
}

function CachedTotalAttack()
{
  global $combatChainState, $CCS_CachedTotalAttack;
  return $combatChainState[$CCS_CachedTotalAttack];
}

function CachedTotalBlock()
{
  global $combatChainState, $CCS_CachedTotalBlock;
  return $combatChainState[$CCS_CachedTotalBlock];
}

function CachedDominateActive()
{
  global $combatChainState, $CCS_CachedDominateActive;
  return ($combatChainState[$CCS_CachedDominateActive] == "1" ? true : false);
}

function CachedOverpowerActive()
{
  global $combatChainState, $CCS_CachedOverpowerActive;
  return ($combatChainState[$CCS_CachedOverpowerActive] == "1" ? true : false);
}

function CachedNumBlockedFromHand() //Dominate
{
  global $combatChainState, $CCS_CachedNumBlockedFromHand;
  return $combatChainState[$CCS_CachedNumBlockedFromHand];
}

function CachedNumDefendedFromHand() //Reprise
{
  global $combatChainState, $CCS_CachedNumDefendedFromHand;
  return $combatChainState[$CCS_CachedNumDefendedFromHand];
}

function CachedNumActionBlocked()
{
  global $combatChainState, $CSS_CachedNumActionBlocked;
  return $combatChainState[$CSS_CachedNumActionBlocked];
}

function AddFloatingMemoryChoice($fromDQ=false)
{
  global $currentPlayer;
  if($fromDQ)
  {

  }
  else {
    $items = &GetItems($currentPlayer);
    for($i=0; $i<count($items); $i+=ItemPieces()) {
      switch($items[$i]) {
        case "h23qu7d6so"://Temporal Spectrometer
          AddDecisionQueue("YESNO", $currentPlayer, "if you want to sacrifice Temporal Spectrometer to reduce the cost");
          AddDecisionQueue("NOPASS", $currentPlayer, "-");
          AddDecisionQueue("PASSPARAMETER", $currentPlayer, "MYITEMS-" . $i, 1);
          AddDecisionQueue("MZBANISH", $currentPlayer, "PLAY," . $items[$i], 1);
          AddDecisionQueue("MZREMOVE", $currentPlayer, "-", 1);
          for($j=0; $j<$items[$i+1]; $j++) {
            AddDecisionQueue("DECDQVAR", $currentPlayer, "0", 1);
          }
          break;
        default: break;
      }
    }
    AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "MYDISCARD:floatingMemoryOnly=true");
    AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a floating memory card to banish", 1);
    AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
    AddDecisionQueue("MZREMOVE", $currentPlayer, "-", 1);
    AddDecisionQueue("MULTIBANISH", $currentPlayer, "GY,-", 1);
    AddDecisionQueue("DECDQVAR", $currentPlayer, "0", 1);
  }
}

function StartTurnAbilities()
{
  global $mainPlayer, $defPlayer;
  MZStartTurnMayAbilities();
  AuraStartTurnAbilities();
  ItemStartTurnAbilities();
}

function MZStartTurnMayAbilities()
{
  global $mainPlayer;
  AddDecisionQueue("FINDINDICES", $mainPlayer, "MZSTARTTURN");
  AddDecisionQueue("SETDQCONTEXT", $mainPlayer, "Choose a start turn ability to activate (or pass)", 1);
  AddDecisionQueue("MAYCHOOSEMULTIZONE", $mainPlayer, "<-", 1);
  AddDecisionQueue("MZSTARTTURNABILITY", $mainPlayer, "-", 1);
}

function MZStartTurnIndices()
{
  global $mainPlayer;
  $mainDiscard = &GetDiscard($mainPlayer);
  $cards = "";
  for($i=0; $i<count($mainDiscard); $i+=DiscardPieces())
  {
    switch($mainDiscard[$i])
    {
      case "UPR086":
        if(ThawIndices($mainPlayer) != "")
        {
          $cards = CombineSearches($cards, SearchMultiZoneFormat($i, "MYDISCARD")); break;
        }
      default: break;
    }
  }
  return $cards;
}

function ArsenalStartTurnAbilities()
{
  global $mainPlayer;
  $arsenal = &GetArsenal($mainPlayer);
  for($i=0; $i<count($arsenal); $i+=ArsenalPieces())
  {
    switch($arsenal[$i])
    {
      case "MON404": case "MON405": case "MON406": case "MON407": case "DVR007": case "RVD007":
        if($arsenal[$i+1] == "DOWN")
        {
          AddDecisionQueue("YESNO", $mainPlayer, "if_you_want_to_turn_your_mentor_card_face_up");
          AddDecisionQueue("NOPASS", $mainPlayer, "-");
          AddDecisionQueue("PASSPARAMETER", $mainPlayer, $i, 1);
          AddDecisionQueue("TURNARSENALFACEUP", $mainPlayer, $i, 1);
        }
        break;
      default: break;
    }
  }
}

function ArsenalAttackAbilities()
{
  global $combatChain, $mainPlayer;
  $attackID = $combatChain[0];
  $attackType = CardType($attackID);
  $attackVal = AttackValue($attackID);
  $arsenal = GetArsenal($mainPlayer);
  for($i=0; $i<count($arsenal); $i+=ArsenalPieces())
  {
    switch($arsenal[$i])
    {

      default: break;
    }
  }
}

function ArsenalAttackModifier()
{
  global $combatChain, $mainPlayer;
  $attackID = $combatChain[0];
  $attackType = CardType($attackID);
  $arsenal = GetArsenal($mainPlayer);
  $modifier = 0;
  for($i=0; $i<count($arsenal); $i+=ArsenalPieces())
  {
    switch($arsenal[$i])
    {
      default: break;
    }
  }
  return $modifier;
}

function ArsenalHitEffects()
{
  global $combatChain, $mainPlayer;
  $attackID = $combatChain[0];
  $attackType = CardType($attackID);
  $attackSubType = CardSubType($attackID);
  $arsenal = GetArsenal($mainPlayer);
  $modifier = 0;
  for($i=0; $i<count($arsenal); $i+=ArsenalPieces())
  {
    switch($arsenal[$i])
    {

      default: break;
    }
  }
  return $modifier;
}

function CharacterPlayCardAbilities($cardID, $from)
{
  global $currentPlayer, $CS_NumLess3PowAAPlayed, $CS_NumAttacks;
  $character = &GetPlayerCharacter($currentPlayer);
  for($i=0; $i<count($character); $i+=CharacterPieces())
  {
    if($character[$i+1] != 2) continue;
    $characterID = ShiyanaCharacter($character[$i]);
    switch($characterID)
    {

      default:
        break;
    }
  }
  $otherPlayer = ($currentPlayer == 1 ? 2 : 1);
  $otherCharacter = &GetPlayerCharacter($otherPlayer);
  for($i=0; $i<count($otherCharacter); $i+=CharacterPieces())
  {
    $characterID = $otherCharacter[$i];
    switch($characterID)
    {
      default:
        break;
    }
  }
}

function MainCharacterPlayCardAbilities($cardID, $from)
{
  global $currentPlayer, $mainPlayer, $CS_NumNonAttackCards, $CS_NumBoostPlayed;
  $character = &GetPlayerCharacter($currentPlayer);
  for($i = 0; $i < count($character); $i += CharacterPieces()) {
    if($character[$i+1] != 2) continue;
    switch($character[$i]) {
      case "zdIhSL5RhK": case "g92bHLtTNl": case "6ILtLfjQEe":
        if(ClassContains($cardID, "MAGE"))
        {
          PlayAura("ENLIGHTEN", $currentPlayer);
          $character[$i+1] = 1;
        }
        break;
      default:
        break;
    }
  }
}

function ArsenalPlayCardAbilities($cardID)
{
  global $currentPlayer;
  $cardType = CardType($cardID);
  $arsenal = GetArsenal($currentPlayer);
  for($i=0; $i<count($arsenal); $i+=ArsenalPieces())
  {
    switch($arsenal[$i])
    {
      default: break;
    }
  }
}

function HasIncreasedAttack()
{
  global $combatChain;
  if(count($combatChain) > 0)
  {
    $attack = 0;
    $defense = 0;
    EvaluateCombatChain($attack, $defense);
    if($attack > AttackValue($combatChain[0])) return true;
  }
  return false;
}

function DamageTrigger($player, $damage, $type, $source="NA", $canPass=false)
{
  AddDecisionQueue("DEALDAMAGE", $player, $damage . "-" . $source . "-" . $type, ($canPass ? 1 : "0"));
  return $damage;
}

function CanDamageBePrevented($player, $damage, $type, $source="-")
{
  global $mainPlayer;
  if($source == "aebjvwbciz" && IsClassBonusActive($mainPlayer, "GUARDIAN") && CharacterLevel($mainPlayer) >= 2) return false;
  return true;
}

function DealDamageAsync($player, $damage, $type="DAMAGE", $source="NA")
{
  global $CS_DamagePrevention, $combatChainState, $combatChain, $mainPlayer;
  global $CCS_AttackFused, $CS_ArcaneDamagePrevention, $currentPlayer, $dqVars, $dqState;

  $classState = &GetPlayerClassState($player);
  $Items = &GetItems($player);
  if($type == "COMBAT" && $damage > 0 && EffectPreventsHit()) HitEffectsPreventedThisLink();
  if($type == "COMBAT" || $type == "ATTACKHIT") $source = $combatChain[0];
  $otherPlayer = $player == 1 ? 2 : 1;
  $damage = $damage > 0 ? $damage : 0;
  $damageThreatened = $damage;
  $preventable = CanDamageBePrevented($player, $damage, $type, $source);
  if($preventable)
  {
    $damage = CurrentEffectPreventDamagePrevention($player, $type, $damage, $source);
    if(ConsumeDamagePrevention($player)) return 0;//If damage can be prevented outright, don't use up your limited damage prevention
    if($type == "ARCANE")
    {
      if($damage <= $classState[$CS_ArcaneDamagePrevention])
      {
        $classState[$CS_ArcaneDamagePrevention] -= $damage;
        $damage = 0;
      }
      else
      {
        $damage -= $classState[$CS_ArcaneDamagePrevention];
        $classState[$CS_ArcaneDamagePrevention] = 0;
      }
    }
    if($damage <= $classState[$CS_DamagePrevention])
    {
      $classState[$CS_DamagePrevention] -= $damage;
      $damage = 0;
    }
    else
    {
      $damage -= $classState[$CS_DamagePrevention];
      $classState[$CS_DamagePrevention] = 0;
    }
  }
  //else: CR 2.0 6.4.10h If damage is not prevented, damage prevention effects are not consumed
  $damage = $damage > 0 ? $damage : 0;
  $damage = CurrentEffectDamagePrevention($player, $type, $damage, $source, $preventable);
  $damage = AuraTakeDamageAbilities($player, $damage, $type);
  $damage = PermanentTakeDamageAbilities($player, $damage, $type);
  $damage = ItemTakeDamageAbilities($player, $damage, $type);
  if($damage == 1 && $preventable && SearchItemsForCard("EVR069", $player) != "") $damage = 0;//Must be last
  $dqVars[0] = $damage;
  if($type == "COMBAT") $dqState[6] = $damage;
  PrependDecisionQueue("FINALIZEDAMAGE", $player, $damageThreatened . "," . $type . "," . $source);
  if($damage > 0)
  {
    AddDamagePreventionSelection($player, $damage, $preventable);
  }
  return $damage;
}

function AddDamagePreventionSelection($player, $damage, $preventable)
{
  PrependDecisionQueue("PROCESSDAMAGEPREVENTION", $player, $damage . "-" . $preventable, 1);
  PrependDecisionQueue("CHOOSEMULTIZONE", $player, "<-", 1);
  PrependDecisionQueue("SETDQCONTEXT", $player, "Choose a card to prevent damage", 1);
  PrependDecisionQueue("FINDINDICES", $player, "DAMAGEPREVENTION");
}

function FinalizeDamage($player, $damage, $damageThreatened, $type, $source)
{
  global $otherPlayer, $CS_DamageTaken, $combatChainState, $CCS_AttackTotalDamage, $CS_ArcaneDamageTaken, $defPlayer, $mainPlayer;
  global $CCS_AttackFused;
  $classState = &GetPlayerClassState($player);
  $otherPlayer = $player == 1 ? 2 : 1;
  if($damage > 0)
  {
    if($source != "NA")
    {
      $damage += CurrentEffectDamageModifiers($player, $source, $type);
      if($type == "COMBAT" && HasCleave($source)) DamagePlayerAllies($player, $damage, $source, $type);
    }

    AuraDamageTakenAbilities($player, $damage);
    ItemDamageTakenAbilities($player, $damage);
    CharacterDamageTakenAbilities($player, $damage);
    CharacterDealDamageAbilities($otherPlayer, $damage);
    $classState[$CS_DamageTaken] += $damage;
    if($player == $defPlayer && $type == "COMBAT" || $type == "ATTACKHIT") $combatChainState[$CCS_AttackTotalDamage] += $damage;
    if($type == "ARCANE") $classState[$CS_ArcaneDamageTaken] += $damage;
    CurrentEffectDamageEffects($player, $source, $type, $damage);
  }
  PlayerLoseHealth($player, $damage);
  LogDamageStats($player, $damageThreatened, $damage);
  return $damage;
}

function DoQuell($targetPlayer, $damage)
{
  $quellChoices = QuellChoices($targetPlayer, $damage);
  if ($quellChoices != "0") {
    PrependDecisionQueue("PAYRESOURCES", $targetPlayer, "<-", 1);
    PrependDecisionQueue("AFTERQUELL", $targetPlayer, "-", 1);
    PrependDecisionQueue("BUTTONINPUT", $targetPlayer, $quellChoices);
    PrependDecisionQueue("SETDQCONTEXT", $targetPlayer, "Choose an amount to pay for Quell");
  } else {
    PrependDecisionQueue("PASSPARAMETER", $targetPlayer, "0"); //If no quell, we need to discard the previous last result
  }
}

function ProcessDealDamageEffect($cardID)
{
  $set = CardSet($cardID);
  if($set == "UPR") {
    return UPRDealDamageEffect($cardID);
  }
}

function ArcaneDamagePrevented($player, $cardMZIndex)
{
  $prevented = 0;
  $params = explode("-", $cardMZIndex);
  $zone = $params[0];
  $index = $params[1];
  switch($zone)
  {
    case "MYCHAR": $source = &GetPlayerCharacter($player); break;
    case "MYITEMS": $source = &GetItems($player); break;
    case "MYAURAS": $source = &GetAuras($player); break;
  }
  if($zone == "MYCHAR" && $source[$index+1] == 0) return;
  $cardID = $source[$index];
  $spellVoidAmount = SpellVoidAmount($cardID, $player);
  if($spellVoidAmount > 0)
  {
    if($zone == "MYCHAR") DestroyCharacter($player, $index);
    else if($zone == "MYITEMS") DestroyItemForPlayer($player, $index);
    else if($zone == "MYAURAS") DestroyAura($player, $index);
    $prevented += $spellVoidAmount;
    WriteLog(CardLink($cardID, $cardID) . " was destroyed and prevented " . $spellVoidAmount . " arcane damage.");
  }
  return $prevented;
}

function CurrentEffectDamageModifiers($player, $source, $type)
{
  global $currentTurnEffects;
  $modifier = 0;
  for($i=count($currentTurnEffects)-CurrentTurnPieces(); $i >= 0; $i-=CurrentTurnPieces())
  {
    $remove = 0;
    switch($currentTurnEffects[$i])
    {
      default: break;
    }
    if($remove == 1) RemoveCurrentTurnEffect($i);
  }
  return $modifier;
}

function CurrentEffectDamageEffects($target, $source, $type, $damage)
{
  global $currentTurnEffects;
  if(CardType($source) == "AA" && (SearchAuras("CRU028", 1) || SearchAuras("CRU028", 2))) return;
  for($i=count($currentTurnEffects)-CurrentTurnPieces(); $i >= 0; $i-=CurrentTurnPieces())
  {
    if($currentTurnEffects[$i+1] == $target) continue;
    if($type == "COMBAT" && HitEffectsArePrevented()) continue;
    $remove = 0;
    switch($currentTurnEffects[$i])
    {

      default: break;
    }
    if($remove == 1) RemoveCurrentTurnEffect($i);
  }
}

function AttackDamageAbilities($damageDone)
{
  global $combatChain, $defPlayer;
  $attackID = $combatChain[0];
  switch($attackID)
  {
    default: break;
  }
}

function LoseHealth($amount, $player)
{
  PlayerLoseHealth($player, $amount);
}

function Restore($amount, $player)
{
  if(SearchCurrentTurnEffects("7533529264", $player)) {
    WriteLog("<span style='color:red;'>Wolffe prevents the healing</span>");
    return false;
  }
  $health = &GetHealth($player);
  WriteLog("Player " . $player . " gained " . $amount . " health.");
  $health -= $amount;
  if($health < 0) $health = 0;
  return true;
}

function PlayerLoseHealth($player, $amount)
{
  $health = &GetHealth($player);
  $amount = AuraLoseHealthAbilities($player, $amount);
  $char = &GetPlayerCharacter($player);
  if(count($char) == 0) return;
  $health += $amount;
  if($health >= CardHP($char[0]))
  {
    PlayerWon(($player == 1 ? 2 : 1));
  }
}

function PlayerRemainingHealth($player) {
  $health = &GetHealth($player);
  $char = &GetPlayerCharacter($player);
  return CardHP($char[0]) - $health;
}

function IsGameOver()
{
  global $inGameStatus, $GameStatus_Over;
  return $inGameStatus == $GameStatus_Over;
}

function PlayerWon($playerID)
{
  global $winner, $turn, $gameName, $p1id, $p2id, $p1uid, $p2uid, $p1IsChallengeActive, $p2IsChallengeActive, $conceded, $currentTurn;
  global $p1DeckLink, $p2DeckLink, $inGameStatus, $GameStatus_Over, $firstPlayer, $p1deckbuilderID, $p2deckbuilderID;
  if($turn[0] == "OVER") return;
  include_once "./MenuFiles/ParseGamefile.php";

  $winner = $playerID;
  if ($playerID == 1 && $p1uid != "") WriteLog($p1uid . " wins!", $playerID);
  elseif ($playerID == 2 && $p2uid != "") WriteLog($p2uid . " wins!", $playerID);
  else WriteLog("Player " . $winner . " wins!");

  $inGameStatus = $GameStatus_Over;
  $turn[0] = "OVER";
  try {
    logCompletedGameStats();
  } catch (Exception $e) {

  }

  if(!$conceded || $currentTurn >= 3) {
    //If this happens, they left a game in progress -- add disconnect logging?
  }
}

function UnsetBanishModifier($player, $modifier, $newMod="DECK")
{
  $banish = &GetBanish($player);
  for($i=0; $i<count($banish); $i+=BanishPieces())
  {
    $cardModifier = explode("-", $banish[$i+1])[0];
    if($cardModifier == $modifier) $banish[$i+1] = $newMod;
  }
}

function UnsetChainLinkBanish()
{
  UnsetBanishModifier(1, "TCL");
  UnsetBanishModifier(2, "TCL");
}

function UnsetCombatChainBanish()
{
  UnsetBanishModifier(1, "TCC");
  UnsetBanishModifier(2, "TCC");
  UnsetBanishModifier(1, "TCL");
  UnsetBanishModifier(2, "TCL");
}

function ReplaceBanishModifier($player, $oldMod, $newMod)
{
  UnsetBanishModifier($player, $oldMod, $newMod);
}

function UnsetTurnBanish()
{
  global $defPlayer;
  UnsetBanishModifier(1, "TT");
  UnsetBanishModifier(1, "INST");
  UnsetBanishModifier(2, "TT");
  UnsetBanishModifier(2, "INST");
  UnsetBanishModifier(1, "ARC119");
  UnsetBanishModifier(2, "ARC119");
  UnsetCombatChainBanish();
  ReplaceBanishModifier($defPlayer, "NT", "TT");
}

function GetChainLinkCards($playerID="", $cardType="", $exclCardTypes="")
{
  global $combatChain;
  $pieces = "";
  $exclArray=explode(",", $exclCardTypes);
  for($i=0; $i<count($combatChain); $i+=CombatChainPieces())
  {
    $thisType = CardType($combatChain[$i]);
    if(($playerID == "" || $combatChain[$i+1] == $playerID) && ($cardType == "" || $thisType == $cardType))
    {
      $excluded = false;
      for($j=0; $j<count($exclArray); ++$j)
      {
        if($thisType == $exclArray[$j]) $excluded = true;
      }
      if($excluded) continue;
      if($pieces != "") $pieces .= ",";
      $pieces .= $i;
    }
  }
  return $pieces;
}

function GetTheirEquipmentChoices()
{
  global $currentPlayer;
  return GetEquipmentIndices(($currentPlayer == 1 ? 2 : 1));
}

function FindMyCharacter($cardID)
{
  global $currentPlayer;
  return FindCharacterIndex($currentPlayer, $cardID);
}

function FindDefCharacter($cardID)
{
  global $defPlayer;
  return FindCharacterIndex($defPlayer, $cardID);
}

function ChainLinkResolvedEffects()
{
  global $combatChain, $mainPlayer, $currentTurnEffects;
  if($combatChain[0] == "MON245" && !ExudeConfidenceReactionsPlayable())
  {
    AddCurrentTurnEffect($combatChain[0], $mainPlayer, "CC");
  }
  switch($combatChain[0])
  {
    case "CRU051": case "CRU052":
      EvaluateCombatChain($totalAttack, $totalBlock);
      for ($i = CombatChainPieces(); $i < count($combatChain); $i += CombatChainPieces()) {
        if (!($totalBlock > 0 && (intval(BlockValue($combatChain[$i])) + BlockModifier($combatChain[$i], "CC", 0) + $combatChain[$i + 6]) > $totalAttack)) {
          UndestroyCurrentWeapon();
        }
      }
      break;
      default: break;
  }
}

function CombatChainClosedMainCharacterEffects()
{
  global $chainLinks, $chainLinkSummary, $combatChain, $mainPlayer;
  $character = &GetPlayerCharacter($mainPlayer);
  for($i=0; $i<count($chainLinks); ++$i)
  {
    for($j=0; $j<count($chainLinks[$i]); $j += ChainLinksPieces())
    {
      if($chainLinks[$i][$j+1] != $mainPlayer) continue;
      $charIndex = FindCharacterIndex($mainPlayer, $chainLinks[$i][$j]);
      if($charIndex == -1) continue;
      switch($chainLinks[$i][$j])
      {
        case "CRU051": case "CRU052":
          if($character[$charIndex+7] == "1") DestroyCharacter($mainPlayer, $charIndex);
          break;
        default: break;
      }
    }
  }
}

function CombatChainClosedCharacterEffects()
{
  global $chainLinks, $defPlayer, $chainLinkSummary, $combatChain;
  $character = &GetPlayerCharacter($defPlayer);
  for($i=0; $i<count($chainLinks); ++$i)
  {
    $nervesOfSteelActive = $chainLinkSummary[$i*ChainLinkSummaryPieces()+1] <= 2 && SearchAuras("EVR023", $defPlayer);
    for($j=0; $j<count($chainLinks[$i]); $j += ChainLinksPieces())
    {
      if($chainLinks[$i][$j+1] != $defPlayer) continue;
      $charIndex = FindCharacterIndex($defPlayer, $chainLinks[$i][$j]);
      if($charIndex == -1) continue;
      if(!$nervesOfSteelActive)
      {
        if(HasTemper($chainLinks[$i][$j]))
        {
          $character[$charIndex+4] -= 1;//Add -1 block counter
          if((BlockValue($character[$charIndex]) + $character[$charIndex + 4] + BlockModifier($character[$charIndex], "CC", 0) + $chainLinks[$i][$j + 5]) <= 0)
          {
            DestroyCharacter($defPlayer, $charIndex);
          }
        }
        if(HasBattleworn($chainLinks[$i][$j]))
        {
          $character[$charIndex+4] -= 1;//Add -1 block counter
        }
        else if(HasBladeBreak($chainLinks[$i][$j]))
        {
          DestroyCharacter($defPlayer, $charIndex);
        }
      }
      switch($chainLinks[$i][$j])
      {
        case "MON089":
          if(!DelimStringContains($chainLinkSummary[$i*ChainLinkSummaryPieces()+3], "ILLUSIONIST") && $chainLinkSummary[$i*ChainLinkSummaryPieces()+1] >= 6)
          {
            $character[FindCharacterIndex($defPlayer, "MON089")+1] = 0;
          }
          break;
        case "RVD003":
          Writelog("Processing " . Cardlink($chainLinks[$i][$j], $chainLinks[$i][$j]) . " trigger: ");
          $deck = &GetDeck($defPlayer);
          $rv = "";
          if (count($deck) == 0) $rv .= "Your deck is empty. No card is revealed.";
          $wasRevealed = RevealCards($deck[0]);
          if ($wasRevealed) {
            if (AttackValue($deck[0]) < 6) {
              WriteLog("The card was put on the bottom of your deck.");
              array_push($deck, array_shift($deck));
            }
          }
          break;
        default: break;
      }
    }
  }
}

// CR 2.1 - 5.3.4c A card with the type defense reaction becomes a defending card and is moved onto the current chain link instead of being moved to the graveyard.
function NumDefendedFromHand() //Reprise
{
  global $combatChain, $defPlayer;
  $num = 0;
  for($i=0; $i<count($combatChain); $i += CombatChainPieces())
  {
    if($combatChain[$i+1] == $defPlayer)
    {
      $type = CardType($combatChain[$i]);
      if($type != "I" && $combatChain[$i+2] == "HAND") ++$num;
    }
  }
  return $num;
}

function NumBlockedFromHand() //Dominate
{
  global $combatChain, $defPlayer, $layers;
  $num = 0;
  for ($i = 0; $i < count($combatChain); $i += CombatChainPieces()) {
    if ($combatChain[$i + 1] == $defPlayer) {
      $type = CardType($combatChain[$i]);
      if ($type != "I" && $combatChain[$i + 2] == "HAND") ++$num;
    }
  }
  for ($i = 0; $i < count($layers); $i += LayerPieces()) {
    $params = explode("|", $layers[$i + 2]);
    if ($params[0] == "HAND" && CardType($layers[$i]) == "DR") ++$num;
  }
  return $num;
}

function NumActionBlocked()
{
  global $combatChain, $defPlayer;
  $num = 0;
  for ($i = 0; $i < count($combatChain); $i += CombatChainPieces()) {
    if ($combatChain[$i + 1] == $defPlayer) {
      $type = CardType($combatChain[$i]);
      if ($type == "A" || $type == "AA") ++$num;
    }
  }
  return $num;
}

function NumCardsBlocking()
{
  global $combatChain, $defPlayer;
  $num = 0;
  for($i=0; $i<count($combatChain); $i += CombatChainPieces())
  {
    if($combatChain[$i+1] == $defPlayer)
    {
      $type = CardType($combatChain[$i]);
      if($type != "I" && $type != "C") ++$num;
    }
  }
  return $num;
}

function NumCardsNonEquipBlocking()
{
  global $combatChain, $defPlayer;
  $num = 0;
  for ($i = 0; $i < count($combatChain); $i += CombatChainPieces()) {
    if ($combatChain[$i + 1] == $defPlayer) {
      $type = CardType($combatChain[$i]);
      if ($type != "E" && $type != "I" && $type != "C") ++$num;
    }
  }
  return $num;
}

function NumAttacksBlocking()
{
  global $combatChain, $defPlayer;
  $num = 0;
  for($i=0; $i<count($combatChain); $i += CombatChainPieces())
  {
    if($combatChain[$i+1] == $defPlayer)
    {
      if(CardType($combatChain[$i]) == "AA") ++$num;
    }
  }
  return $num;
}

function NumActionsBlocking()
{
  global $combatChain, $defPlayer;
  $num = 0;
  for($i=0; $i<count($combatChain); $i += CombatChainPieces())
  {
    if($combatChain[$i+1] == $defPlayer)
    {
      $cardType = CardType($combatChain[$i]);
      if($cardType == "A" || $cardType == "AA") ++$num;
    }
  }
  return $num;
}

function NumNonAttackActionBlocking()
{
  global $combatChain, $defPlayer;
  $num = 0;
  for($i=0; $i<count($combatChain); $i += CombatChainPieces())
  {
    if($combatChain[$i+1] == $defPlayer)
    {
      $type = CardType($combatChain[$i]);
      if($type == "A") ++$num;
    }
  }
  return $num;
}

function NumReactionBlocking()
{
  global $combatChain, $defPlayer;
  $num = 0;
  for($i=0; $i<count($combatChain); $i += CombatChainPieces())
  {
    if($combatChain[$i+1] == $defPlayer)
    {
      $type = CardType($combatChain[$i]);
      if($type == "AR" || $type == "DR") ++$num;
    }
  }
  return $num;
}

function IHaveLessHealth()
{
  global $currentPlayer;
  return PlayerHasLessHealth($currentPlayer);
}

function DefHasLessHealth()
{
  global $defPlayer;
  return PlayerHasLessHealth($defPlayer);
}

function PlayerHasLessHealth($player)
{
  $otherPlayer = ($player == 1 ? 2 : 1);
  return GetHealth($player) < GetHealth($otherPlayer);
}

function PlayerHasFewerEquipment($player)
{
  $otherPlayer = ($player == 1 ? 2 : 1);
  $thisChar = &GetPlayerCharacter($player);
  $thatChar = &GetPlayerCharacter($otherPlayer);
  $thisEquip = 0;
  $thatEquip = 0;
  for($i=0; $i<count($thisChar); $i+=CharacterPieces())
  {
    if($thisChar[$i+1] != 0 && CardType($thisChar[$i]) == "E") ++$thisEquip;
  }
  for($i=0; $i<count($thatChar); $i+=CharacterPieces())
  {
    if($thatChar[$i+1] != 0 && CardType($thatChar[$i]) == "E") ++$thatEquip;
  }
  return $thisEquip < $thatEquip;
}

function GetIndices($count, $add=0, $pieces=1)
{
  $indices = "";
  for($i=0; $i<$count; $i+=$pieces)
  {
    if($indices != "") $indices .= ",";
    $indices .= ($i + $add);
  }
  return $indices;
}

function GetMyHandIndices()
{
  global $currentPlayer;
  return GetIndices(count(GetHand($currentPlayer)));
}

function GetDefHandIndices()
{
  global $defPlayer;
  return GetIndices(count(GetHand($defPlayer)));
}

function CurrentAttack()
{
  global $combatChain;
  if(count($combatChain) == 0) return "";
  return $combatChain[0];
}

function RollDie($player, $fromDQ=false, $subsequent=false)
{
  global $CS_DieRoll;
  $numRolls = 1 + CountCurrentTurnEffects("EVR003", $player);
  $highRoll = 0;
  for($i=0; $i<$numRolls; ++$i)
  {
    $roll = GetRandom(1, 6);
    WriteLog($roll . " was rolled.");
    if($roll > $highRoll) $highRoll = $roll;
  }
  AddEvent("ROLL", $highRoll);
  SetClassState($player, $CS_DieRoll, $highRoll);
  $GGActive = HasGamblersGloves(1) || HasGamblersGloves(2);
  if($GGActive)
  {
    if($fromDQ && !$subsequent) PrependDecisionQueue("AFTERDIEROLL", $player, "-");
    GamblersGloves($player, $player, $fromDQ);
    GamblersGloves(($player == 1 ? 2 : 1), $player, $fromDQ);
    if(!$fromDQ && !$subsequent) AddDecisionQueue("AFTERDIEROLL", $player, "-");
  }
  else
  {
    if(!$subsequent) AfterDieRoll($player);
  }
}

function AfterDieRoll($player)
{
  global $CS_DieRoll, $CS_HighestRoll;
  $roll = GetClassState($player, $CS_DieRoll);
  $skullCrusherIndex = FindCharacterIndex($player, "EVR001");
  if($skullCrusherIndex > -1 && IsCharacterAbilityActive($player, $skullCrusherIndex))
  {
    if($roll == 1) { WriteLog("Skull Crushers was destroyed."); DestroyCharacter($player, $skullCrusherIndex); }
    if($roll == 5 || $roll == 6) { WriteLog("Skull Crushers gives +1 this turn."); AddCurrentTurnEffect("EVR001", $player); }
  }
  if($roll > GetClassState($player, $CS_HighestRoll)) SetClassState($player, $CS_HighestRoll, $roll);
}

function HasGamblersGloves($player)
{
  $gamblersGlovesIndex = FindCharacterIndex($player, "CRU179");
  return $gamblersGlovesIndex != -1 && IsCharacterAbilityActive($player, $gamblersGlovesIndex);
}

function GamblersGloves($player, $origPlayer, $fromDQ)
{
  $gamblersGlovesIndex = FindCharacterIndex($player, "CRU179");
  if(HasGamblersGloves($player))
  {
    if($fromDQ)
    {
      PrependDecisionQueue("ROLLDIE", $origPlayer, "1", 1);
      PrependDecisionQueue("DESTROYCHARACTER", $player, "-", 1);
      PrependDecisionQueue("PASSPARAMETER", $player, $gamblersGlovesIndex, 1);
      PrependDecisionQueue("NOPASS", $player, "-");
      PrependDecisionQueue("YESNO", $player, "if_you_want_to_destroy_Gambler's_Gloves_to_reroll_the_result");
    }
    else
    {
      AddDecisionQueue("YESNO", $player, "if_you_want_to_destroy_Gambler's_Gloves_to_reroll_the_result");
      AddDecisionQueue("NOPASS", $player, "-");
      AddDecisionQueue("PASSPARAMETER", $player, $gamblersGlovesIndex, 1);
      AddDecisionQueue("DESTROYCHARACTER", $player, "-", 1);
      AddDecisionQueue("ROLLDIE", $origPlayer, "1", 1);
    }
  }
}

function IsCharacterAbilityActive($player, $index, $checkGem=false)
{
  $character = &GetPlayerCharacter($player);
  if($checkGem && $character[$index+9] == 0) return false;
  return $character[$index+1] == 2;
}

function GetDieRoll($player)
{
  global $CS_DieRoll;
  return GetClassState($player, $CS_DieRoll);
}

function ClearDieRoll($player)
{
  global $CS_DieRoll;
  return SetClassState($player, $CS_DieRoll, 0);
}

function CanPlayAsInstant($cardID, $index=-1, $from="")
{
  global $currentPlayer, $CS_NextWizardNAAInstant, $CS_NextNAAInstant, $CS_CharacterIndex, $CS_ArcaneDamageTaken, $CS_NumWizardNonAttack;
  global $mainPlayer, $CS_PlayedAsInstant;
  $otherPlayer = $currentPlayer == 1 ? 2 : 1;
  $cardType = CardType($cardID);
  $otherCharacter = &GetPlayerCharacter($otherPlayer);
  if($cardID == "MON034" && SearchItemsForCard("DYN066", $currentPlayer) != "") return true;
  if(GetClassState($currentPlayer, $CS_NextWizardNAAInstant))
  {
    if(ClassContains($cardID, "WIZARD", $currentPlayer) && $cardType == "A") return true;
  }
  if(GetClassState($currentPlayer, $CS_NumWizardNonAttack) && ($cardID == "CRU174" || $cardID == "CRU175" || $cardID == "CRU176")) return true;
  if($currentPlayer != $mainPlayer && ($cardID == "CRU165" || $cardID == "CRU166" || $cardID == "CRU167")) return true;
  if(GetClassState($currentPlayer, $CS_NextNAAInstant))
  {
    if($cardType == "A") return true;
  }
  if($cardType == "C" || $cardType == "E" || $cardType == "W")
  {
    if($index == -1) $index = GetClassState($currentPlayer, $CS_CharacterIndex);
    if(SearchCharacterEffects($currentPlayer, $index, "INSTANT")) return true;
  }
  if($from == "BANISH")
  {
    $banish = GetBanish($currentPlayer);
    if($index < count($banish))
    {
      $mod = explode("-", $banish[$index+1])[0];
      if(($cardType == "I" && ($mod == "TCL" || $mod == "TT" || $mod == "TCC" || $mod == "NT" || $mod == "MON212")) || $mod == "INST" || $mod == "ARC119") return true;
    }
  }
  if(GetClassState($currentPlayer, $CS_PlayedAsInstant) == "1") return true;
  if($cardID == "ELE106" || $cardID == "ELE107" || $cardID == "ELE108") { return PlayerHasFused($currentPlayer); }
  if($cardID == "CRU143") { return GetClassState($otherPlayer, $CS_ArcaneDamageTaken) > 0; }
  if($from == "ARS" && $cardType == "A" && $currentPlayer != $mainPlayer && PitchValue($cardID) == 3 && (SearchCharacterActive($currentPlayer, "EVR120") || SearchCharacterActive($currentPlayer, "UPR102") || SearchCharacterActive($currentPlayer, "UPR103") || (SearchCharacterActive($currentPlayer, "CRU097") && SearchCurrentTurnEffects($otherCharacter[0] . "-SHIYANA", $currentPlayer) && IsIyslander($otherCharacter[0])))) return true;
  $isStaticType = IsStaticType($cardType, $from, $cardID);
  $abilityType = "-";
  if($isStaticType) $abilityType = GetAbilityType($cardID, $index, $from);
  if(($cardType == "AR" || ($abilityType == "AR" && $isStaticType)) && IsReactionPhase() && $currentPlayer == $mainPlayer) return true;
  if(($cardType == "DR" || ($abilityType == "DR" && $isStaticType)) && IsReactionPhase() && $currentPlayer != $mainPlayer && IsDefenseReactionPlayable($cardID, $from)) return true;
  return false;
}

function HasLostClass($player)
{
  if(SearchCurrentTurnEffects("UPR187", $player)) return true;//Erase Face
  return false;
}

function ClassOverride($cardID, $player="")
{
  global $currentTurnEffects;
  $cardClass = CardClass($cardID);
  if ($cardClass == "NONE") $cardClass = "";
  $otherPlayer = ($player == 1 ? 2 : 1);
  $otherCharacter = &GetPlayerCharacter($otherPlayer);

  if(SearchCurrentTurnEffects("UPR187", $player)) return "NONE";//Erase Face
  if(count($otherCharacter) > 0 && SearchCurrentTurnEffects($otherCharacter[0] . "-SHIYANA", $player)) {
    if($cardClass != "") $cardClass .= ",";
    $cardClass .= CardClass($otherCharacter[0]) . ",SHAPESHIFTER";
  }

  for($i=0; $i<count($currentTurnEffects); $i+=CurrentTurnEffectPieces())
  {
    if($currentTurnEffects[$i+1] != $player) continue;
    $toAdd = "";
    switch($currentTurnEffects[$i])
    {
      case "MON095": case "MON096": case "MON097": $toAdd = "ILLUSIONIST";
      case "EVR150": case "EVR151": case "EVR152": $toAdd = "ILLUSIONIST";
      case "UPR155": case "UPR156": case "UPR157": $toAdd = "ILLUSIONIST";
      default: break;
    }
    if($toAdd != "")
    {
      if($cardClass != "") $cardClass .= ",";
      $cardClass .= $toAdd;
    }
  }
  if($cardClass == "") return "NONE";
  return $cardClass;
}

function NameOverride($cardID, $player="")
{
  $name = CardName($cardID);
  if(SearchCurrentTurnEffects("OUT183", $player)) $name = "";
  return $name;
}

function DefinedTypesContains($cardID, $type, $player="")
{
  $cardTypes = DefinedCardType($cardID);
  $cardTypes2 = DefinedCardType2($cardID);
  return DelimStringContains($cardTypes, $type) || DelimStringContains($cardTypes2, $type);
}

function CardTypeContains($cardID, $type, $player="")
{
  $cardTypes = CardTypes($cardID);
  return DelimStringContains($cardTypes, $type);
}

function ClassContains($cardID, $class, $player="")
{
  $cardClass = ClassOverride($cardID, $player);
  return DelimStringContains($cardClass, $class);
}

function AspectContains($cardID, $aspect, $player="")
{
  $cardAspect = CardAspects($cardID);
  return DelimStringContains($cardAspect, $aspect);
}

function TraitContains($cardID, $trait, $player="")
{
  $cardTrait = CardTraits($cardID);
  return DelimStringContains($cardTrait, $trait);
}

function ArenaContains($cardID, $arena, $player="")
{
  $cardArena = CardArenas($cardID);
  return DelimStringContains($cardArena, $arena);
}

function SubtypeContains($cardID, $subtype, $player="")
{
  $cardSubtype = CardSubtype($cardID);
  return DelimStringContains($cardSubtype, $subtype);
}

function ElementContains($cardID, $element, $player="")
{
  $cardElement = CardElement($cardID);
  return DelimStringContains($cardElement, $element);
}

function CardNameContains($cardID, $name, $player="")
{
  $cardName = NameOverride($cardID, $player);
  return DelimStringContains($cardName, $name);
}

function TalentOverride($cardID, $player="")
{
  global $currentTurnEffects;
  $cardTalent = CardTalent($cardID);
  //CR 2.2.1 - 6.3.6. Continuous effects that remove a property, or part of a property, from an object do not remove properties, or parts of properties, that were added by another effect.
  if(SearchCurrentTurnEffects("UPR187", $player)) $cardTalent = "NONE";
  for($i=0; $i<count($currentTurnEffects); $i+=CurrentTurnEffectPieces())
  {
    $toAdd = "";
    if($currentTurnEffects[$i+1] != $player) continue;
    switch($currentTurnEffects[$i])
    {
      case "UPR060": case "UPR061": case "UPR062": $toAdd = "DRACONIC";
      default: break;
    }
    if($toAdd != "")
    {
      if($cardTalent == "NONE") $cardTalent = "";
      if($cardTalent != "") $cardTalent .= ",";
      $cardTalent .= $toAdd;
    }
  }
  return $cardTalent;
}

function TalentContains($cardID, $talent, $player="")
{
  $cardTalent = TalentOverride($cardID, $player);
  return DelimStringContains($cardTalent, $talent);
}

function RevealCards($cards, $player="", $from="HAND")
{
  global $currentPlayer;
  if($player == "") $player = $currentPlayer;
  if(!CanRevealCards($player)) return false;
  $cardArray = explode(",", $cards);
  $string = "";
  for($i=count($cardArray)-1; $i>=0; --$i)
  {
    if($string != "") $string .= ", ";
    $string .= CardLink($cardArray[$i], $cardArray[$i]);
    AddEvent("REVEAL", $cardArray[$i]);
    OnRevealEffect($player, $cardArray[$i], $from, $i);
  }
  $string .= (count($cardArray) == 1 ? " is" : " are");
  $string .= " revealed.";
  WriteLog($string);
  return true;
}

function OnRevealEffect($player, $cardID, $from, $index)
{
  switch($cardID)
  {
    case "uwnHTLG3fL"://Luxem Sight
      if($from != "MEMORY") break;
      WriteLog("Player $player recovered 3 from revealing Luxem Sight");
      Recover($player, 3);
      break;
    case "zxB4tzy9iy"://Lightweaver's Assault
      if($from != "MEMORY") break;
      if(IsClassBonusActive($player, "ASSASSIN")) DealArcane(2, 2, "TRIGGER", $cardID, fromQueue:true, player:$player);
      break;
    case "qufoIF014c"://Gleaming Cut
      if($from != "MEMORY" || !IsClassBonusActive($player, "ASSASSIN")) break;
      AddDecisionQueue("YESNO", $player, "if you want to banish gleaming cut");
      AddDecisionQueue("NOPASS", $player, "-");
      AddDecisionQueue("PASSPARAMETER", $player, "MYMEMORY-" . ($index * MemoryPieces()), 1);
      AddDecisionQueue("MZBANISH", $player, "MEMORY,-," . $player, 1);
      AddDecisionQueue("MZREMOVE", $player, "-", 1);
      AddDecisionQueue("DRAW", $player, "-", 1);
      AddDecisionQueue("DRAW", $player, "-", 1);
      break;
    case "VAFTR5taNG"://Corhazi Infiltrator
      if($from != "MEMORY" || !IsClassBonusActive($player, "ASSASSIN")) break;
      AddDecisionQueue("YESNO", $player, "if you want to put Corhazi Infiltrator into play");
      AddDecisionQueue("NOPASS", $player, "-");
      AddDecisionQueue("PASSPARAMETER", $player, "MYMEMORY-" . ($index * MemoryPieces()), 1);
      AddDecisionQueue("SETDQVAR", $player, "0", 1);
      AddDecisionQueue("MZOP", $player, "GETCARDID", 1);
      AddDecisionQueue("PUTPLAY", $player, "-", 1);
      AddDecisionQueue("PASSPARAMETER", $player, "{0}", 1);
      AddDecisionQueue("MZREMOVE", $player, "-", 1);
      break;
    default: break;
  }
}

function DoesAttackHaveGoAgain()
{
  global $combatChain, $combatChainState, $CCS_CurrentAttackGainedGoAgain, $mainPlayer, $defPlayer, $CS_NumRedPlayed, $CS_NumNonAttackCards;
  global $CS_NumAuras, $CS_ArcaneDamageTaken, $myDeck, $CS_AnotherWeaponGainedGoAgain;

  if(count($combatChain) == 0) return false;//No combat chain, so no
  $attackType = CardType($combatChain[0]);
  $attackSubtype = CardSubType($combatChain[0]);
  if(CurrentEffectPreventsGoAgain()) return false;
  if(HasGoAgain($combatChain[0])) return true;
  if($combatChainState[$CCS_CurrentAttackGainedGoAgain] == 1 || CurrentEffectGrantsGoAgain() || MainCharacterGrantsGoAgain()) return true;
  switch($combatChain[0])
  {

    default: break;
  }
  return false;
}

function IsEquipUsable($player, $index)
{
  $character = &GetPlayerCharacter($player);
  if($index >= count($character) || $index < 0) return false;
  return $character[$index + 1] == 2;
}


function UndestroyCurrentWeapon()
{
  global $combatChainState, $CCS_WeaponIndex, $mainPlayer;
  $index = $combatChainState[$CCS_WeaponIndex];
  $char = &GetPlayerCharacter($mainPlayer);
  $char[$index+7] = "0";
}

function DestroyCurrentWeapon()
{
  global $combatChainState, $CCS_WeaponIndex, $mainPlayer;
  $index = $combatChainState[$CCS_WeaponIndex];
  $char = &GetPlayerCharacter($mainPlayer);
  $char[$index+7] = "1";
}

function AttackDestroyed($attackID)
{
  global $mainPlayer, $combatChainState, $CCS_GoesWhereAfterLinkResolves;
  $type = CardType($attackID);
  $character = &GetPlayerCharacter($mainPlayer);
  switch($attackID)
  {

    default: break;
  }
  AttackDestroyedEffects($attackID);
}

function AttackDestroyedEffects($attackID)
{
  global $currentTurnEffects, $mainPlayer;
  for($i=0; $i<count($currentTurnEffects); $i+=CurrentTurnEffectPieces())
  {
    switch($currentTurnEffects[$i])
    {
      default: break;
    }
  }
}

function CloseCombatChain($chainClosed="true")
{
  global $turn, $currentPlayer, $mainPlayer, $combatChainState, $CCS_AttackTarget, $layers;
  $layers = [];//In case there's another combat chain related layer like defense step
  PrependLayer("FINALIZECHAINLINK", $mainPlayer, $chainClosed);
  $turn[0] = "M";
  $currentPlayer = $mainPlayer;
  $combatChainState[$CCS_AttackTarget] = "NA";
}

function UndestroyCharacter($player, $index)
{
  $char = &GetPlayerCharacter($player);
  $char[$index+1] = 2;
  $char[$index+4] = 0;
}

function DestroyCharacter($player, $index)
{
  $char = &GetPlayerCharacter($player);
  $char[$index+1] = 0;
  $char[$index+4] = 0;
  $cardID = $char[$index];
  if($char[$index+6] == 1) RemoveCombatChain(GetCombatChainIndex($cardID, $player));
  $char[$index+6] = 0;
  AddGraveyard($cardID, $player, "CHAR");
  CharacterDestroyEffect($cardID, $player);
  return $cardID;
}

function RemoveCharacter($player, $index)
{
  $char = &GetPlayerCharacter($player);
  $cardID = $char[$index];
  for($i=$index+CharacterPieces()-1; $i>=$index; --$i)
  {
    unset($char[$i]);
  }
  $char = array_values($char);
  return $cardID;
}

function AddDurabilityCounters($player, $amount=1)
{
  AddDecisionQueue("PASSPARAMETER", $player, $amount);
  AddDecisionQueue("SETDQVAR", $player, "0");
  AddDecisionQueue("MULTIZONEINDICES", $player, "MYCHAR:type=WEAPON");
  AddDecisionQueue("SETDQCONTEXT", $player, "Choose a weapon to add durability counter" . ($amount > 1 ? "s" : ""), 1);
  AddDecisionQueue("CHOOSEMULTIZONE", $player, "<-", 1);
  AddDecisionQueue("MZOP", $player, "ADDDURABILITY", 1);
}

function RemoveCombatChain($index)
{
  global $combatChain;
  if($index < 0) return;
  for($i = CombatChainPieces() - 1; $i >= 0; --$i) {
    unset($combatChain[$index + $i]);
  }
  $combatChain = array_values($combatChain);
}

function RemoveArsenalEffects($player, $cardToReturn){
  SearchCurrentTurnEffects("EVR087", $player, true);
  SearchCurrentTurnEffects("ARC042", $player, true);
  if($cardToReturn == "ARC057" ){SearchCurrentTurnEffects("ARC057", $player, true);}
  if($cardToReturn == "ARC058" ){SearchCurrentTurnEffects("ARC058", $player, true);}
  if($cardToReturn == "ARC059" ){SearchCurrentTurnEffects("ARC059", $player, true);}
}

function LookAtHand($player)
{
  $hand = &GetHand($player);
  $cards = "";
  for($i=0; $i<count($hand); $i+=HandPieces())
  {
    if($cards != "") $cards .= ",";
    $cards .= $hand[$i];
  }
  RevealCards($cards, $player);
}

function GainActionPoints($amount=1, $player=0)
{
  global $actionPoints, $mainPlayer, $currentPlayer;
  if($player == 0) $player = $currentPlayer;
  if($player == $mainPlayer) $actionPoints += $amount;
}

function AddCharacterUses($player, $index, $numToAdd)
{
  $character = &GetPlayerCharacter($player);
  if($character[$index+1] == 0) return;
  $character[$index+1] = 2;
  $character[$index+5] += $numToAdd;
}

function HaveUnblockedEquip($player)
{
  $char = &GetPlayerCharacter($player);
  for($i=CharacterPieces(); $i<count($char); $i+=CharacterPieces())
  {
    if($char[$i+1] == 0) continue;//If broken
    if($char[$i+6] == 1) continue;//On combat chain
    if(CardType($char[$i]) != "E") continue;
    if(BlockValue($char[$i]) == -1) continue;
    return true;
  }
  return false;
}

function NumEquipBlock()
{
  global $combatChain, $defPlayer;
  $numEquipBlock = 0;
  for($i=CombatChainPieces(); $i<count($combatChain); $i+=CombatChainPieces())
  {
    if(CardType($combatChain[$i]) == "E" && $combatChain[$i + 1] == $defPlayer) ++$numEquipBlock;
  }
  return $numEquipBlock;
}

  function CanPassPhase($phase)
  {
    global $combatChainState, $CCS_RequiredEquipmentBlock, $currentPlayer;
    if($phase == "B" && HaveUnblockedEquip($currentPlayer) && NumEquipBlock() < $combatChainState[$CCS_RequiredEquipmentBlock]) return false;
    switch($phase)
    {
      case "P": return 0;
      case "PDECK": return 0;
      case "CHOOSEDECK": return 0;
      case "HANDTOPBOTTOM": return 0;
      case "CHOOSECOMBATCHAIN": return 0;
      case "CHOOSECHARACTER": return 0;
      case "CHOOSEHAND": return 0;
      case "CHOOSEHANDCANCEL": return 0;
      case "MULTICHOOSEDISCARD": return 0;
      case "CHOOSEDISCARDCANCEL": return 0;
      case "CHOOSEARCANE": return 0;
      case "CHOOSEARSENAL": return 0;
      case "CHOOSEDISCARD": return 0;
      case "MULTICHOOSEHAND": return 0;
      case "MULTICHOOSEMATERIAL": return 0;
      case "CHOOSEMULTIZONE": return 0;
      case "CHOOSEBANISH": return 0;
      case "BUTTONINPUTNOPASS": return 0;
      case "CHOOSEFIRSTPLAYER": return 0;
      case "MULTICHOOSEDECK": return 0;
      case "CHOOSEPERMANENT": return 0;
      case "MULTICHOOSETEXT": return 0;
      case "CHOOSEMYSOUL": return 0;
      case "OVER": return 0;
      default: return 1;
    }
  }

  //Returns true if done for that player
  function EndTurnPitchHandling($player)
  {
    global $currentPlayer, $turn;
    $pitch = &GetPitch($player);
    if(count($pitch) == 0)
    {
      return true;
    }
    else if(count($pitch) == 1)
    {
      PitchDeck($player, 0);
      return true;
    }
    else
    {
      $currentPlayer = $player;
      $turn[0] = "PDECK";
      return false;
    }
  }

  function ResolveGoAgain($cardID, $player, $from)
  {
    global $actionPoints;
    ++$actionPoints;
  }

  function PitchDeck($player, $index)
  {
    $deck = &GetDeck($player);
    $cardID = RemovePitch($player, $index);
    array_push($deck, $cardID);
  }

  function GetUniqueId()
  {
    global $permanentUniqueIDCounter;
    ++$permanentUniqueIDCounter;
    return $permanentUniqueIDCounter;
  }

  function IsHeroAttackTarget()
  {
    $target = explode("-", GetAttackTarget());
    return $target[0] == "THEIRCHAR";
  }

  function IsAllyAttackTarget()
  {
    $target = explode("-", GetAttackTarget());
    return $target[0] == "THEIRALLY";
  }

  function AttackIndex()
  {
    global $combatChainState, $CCS_WeaponIndex;
    return $combatChainState[$CCS_WeaponIndex];
  }

  function IsAttackTargetRested()
  {
    global $defPlayer;
    $target = GetAttackTarget();
    $mzArr = explode("-", $target);
    if($mzArr[0] == "ALLY" || $mzArr[0] == "MYALLY" || $mzArr[0] == "THEIRALLY")
    {
      $allies = &GetAllies($defPlayer);
      return $allies[$mzArr[1]+1] == 1;
    }
    else
    {
      $char = &GetPlayerCharacter($defPlayer);
      return $char[1] == 1;
    }
  }

  function IsSpecificAllyAttackTarget($player, $index)
  {
    $mzTarget = GetAttackTarget();
    $mzArr = explode("-", $mzTarget);
    if($mzArr[0] == "ALLY" || $mzArr[0] == "MYALLY" || $mzArr[0] == "THEIRALLY")
    {
      return $index == intval($mzArr[1]);
    }
    return false;
  }

  function IsAllyAttacking()
  {
    global $combatChain;
    if(count($combatChain) == 0) return false;
    return IsAlly($combatChain[0]);
  }

  function IsSpecificAllyAttacking($player, $index)
  {
    global $combatChain, $combatChainState, $CCS_WeaponIndex, $mainPlayer;
    if(count($combatChain) == 0) return false;
    if($mainPlayer != $player) return false;
    $weaponIndex = intval($combatChainState[$CCS_WeaponIndex]);
    if($weaponIndex == -1) return false;
    if($weaponIndex != $index) return false;
    if(!IsAlly($combatChain[0])) return false;
    return true;
  }

  function AttackerMZID($player)
  {
    global $combatChainState, $CCS_WeaponIndex, $mainPlayer;
    if($player == $mainPlayer) return "MYALLY-" . $combatChainState[$CCS_WeaponIndex];
    else return "THEIRALLY-" . $combatChainState[$CCS_WeaponIndex];
  }

function IsSpecificAuraAttacking($player, $index)
{
  global $combatChain, $combatChainState, $CCS_WeaponIndex, $mainPlayer;
  if (count($combatChain) == 0) return false;
  if ($mainPlayer != $player) return false;
  $weaponIndex = intval($combatChainState[$CCS_WeaponIndex]);
  if ($weaponIndex == -1) return false;
  if ($weaponIndex != $index) return false;
  if (!DelimStringContains(CardSubtype($combatChain[0]), "Aura")) return false;
  return true;
}

function RevealMemory($player)
{
  $memory = &GetMemory($player);
  $toReveal = "";
  for($i=0; $i<count($memory); $i += MemoryPieces())
  {
    if($toReveal != "") $toReveal .= ",";
    $toReveal .= $memory[$i];
  }
  return RevealCards($toReveal, $player, "MEMORY");
}

  function CanRevealCards($player)
  {
    return true;
  }

  function BaseAttackModifiers($attackValue)
  {
    global $combatChainState, $CCS_LinkBaseAttack, $currentTurnEffects, $mainPlayer;
    for($i=0; $i<count($currentTurnEffects); $i+=CurrentTurnEffectPieces())
    {
      if($currentTurnEffects[$i+1] != $mainPlayer) continue;
      if(!IsCombatEffectActive($currentTurnEffects[$i])) continue;
      switch($currentTurnEffects[$i])
      {
        case "EVR094": case "EVR095": case "EVR096": $attackValue = ceil($attackValue/2); break;
        default: break;
      }
    }
    return $attackValue;
  }

  function GetDefaultLayerTarget()
  {
    global $layers, $combatChain, $currentPlayer;
    if(count($combatChain) > 0) return $combatChain[0];
    if(count($layers) > 0)
    {
      for($i=count($layers)-LayerPieces(); $i>=0; $i-=LayerPieces())
      {
        if($layers[$i+1] != $currentPlayer) return $layers[$i];
      }
    }
    return "-";
  }

function GetDamagePreventionIndices($player)
{
  $rv = "";
  $auras = &GetAuras($player);
  $indices = "";
  for($i=0; $i<count($auras); $i+=AuraPieces())
  {
    if(AuraDamagePreventionAmount($player, $i) > 0)
    {
      if($indices != "") $indices .= ",";
      $indices .= $i;
    }
  }
  $mzIndices = SearchMultiZoneFormat($indices, "MYAURAS");

  $char = &GetPlayerCharacter($player);
  $indices = "";
  for($i=0; $i<count($char); $i+=CharacterPieces())
  {
    if($char[$i+1] != 0 && WardAmount($char[$i]) > 0)
    {
      if($indices != "") $indices .= ",";
      $indices .= $i;
    }
  }
  $indices = SearchMultiZoneFormat($indices, "MYCHAR");
  $mzIndices = CombineSearches($mzIndices, $indices);

  $ally = &GetAllies($player);
  $indices = "";
  for($i=0; $i<count($ally); $i+=AllyPieces())
  {
    if($ally[$i+1] != 0 && WardAmount($ally[$i]) > 0)
    {
      if($indices != "") $indices .= ",";
      $indices .= $i;
    }
  }
  $indices = SearchMultiZoneFormat($indices, "MYALLY");
  $mzIndices = CombineSearches($mzIndices, $indices);
  $rv = $mzIndices;
  return $rv;
}

function GetDamagePreventionTargetIndices()
{
  global $combatChain, $currentPlayer;
  $otherPlayer = $currentPlayer == 1 ? 2 : 1;
  $rv = "";

  $rv = SearchMultizone($otherPlayer, "LAYER");
  if (count($combatChain) > 0) {
    if ($rv != "") $rv .= ",";
    $rv .= "CC-0";
  }
  if (SearchLayer($otherPlayer, "W") == "" && (count($combatChain) == 0 || CardType($combatChain[0]) != "W")) {
    $theirWeapon = SearchMultiZoneFormat(SearchCharacter($otherPlayer, type: "W"), "THEIRCHAR");
    $rv = CombineSearches($rv, $theirWeapon);
  }
  $theirAllies = SearchMultiZoneFormat(SearchAllies($otherPlayer), "THEIRALLY");
  $rv = CombineSearches($rv, $theirAllies);
  $theirAuras = SearchMultiZoneFormat(SearchAura($otherPlayer), "THEIRAURAS");
  $rv = CombineSearches($rv, $theirAuras);
  $theirHero = SearchMultiZoneFormat(SearchCharacter($otherPlayer, type: "C"), "THEIRCHAR");
  $rv = CombineSearches($rv, $theirHero);
  return $rv;
}

function SameWeaponEquippedTwice()
{
  global $mainPlayer;
  $char = &GetPlayerCharacter($mainPlayer);
  $weaponIndex = explode(",", SearchCharacter($mainPlayer, "W"));
  if (count($weaponIndex) > 1 && $char[$weaponIndex[0]] == $char[$weaponIndex[1]]) return true;
  return false;
}

function SelfCostModifier($cardID)
{
  global $currentPlayer, $CS_NumAttacks, $CS_LastAttack;
  $modifier = 0;
  //Aspect Penalty
  if(!TraitContains($cardID, "Spectre", $currentPlayer) || (HeroCard($currentPlayer) != "7440067052" && SearchAlliesForCard($currentPlayer, "80df3928eb") == "")) {
    $penalty = 0;
    $aspectArr = explode(",", CardAspects($cardID));
    $playerAspects = PlayerAspects($currentPlayer);
    for($i=0; $i<count($aspectArr); ++$i)
    {
      --$playerAspects[$aspectArr[$i]];
      if($playerAspects[$aspectArr[$i]] < 0) ++$penalty;
    }
    $modifier += $penalty * 2;
  }
  //Self Cost Modifier
  switch($cardID) {
    case "1446471743"://Force Choke
      if(SearchCount(SearchAllies($currentPlayer, trait:"Force")) > 0) $modifier -= 1;
      break;
    case "4111616117"://Volunteer Soldier
      if(SearchCount(SearchAllies($currentPlayer, trait:"Trooper")) > 0) $modifier -= 1;
      break;
    default: break;
  }
  //Opponent ally cost modifier
  $otherPlayer = $currentPlayer == 1 ? 2 : 1;
  $allies = &GetAllies($otherPlayer);
  for($i=0; $i<count($allies); $i+=AllyPieces())
  {
    if($allies[$i+1] == 0) continue;
    switch($allies[$i]) {
      case "9412277544"://Del Meeko
        if(DefinedTypesContains($cardID, "Event", $currentPlayer)) $modifier += 1;
        break;
      default: break;
    }
  }
  return $modifier;
}

function PlayerAspects($player)
{
  $char = &GetPlayerCharacter($player);
  $aspects = [];
  $aspects["Vigilance"] = 0;
  $aspects["Command"] = 0;
  $aspects["Aggression"] = 0;
  $aspects["Cunning"] = 0;
  $aspects["Heroism"] = 0;
  $aspects["Villainy"] = 0;
  for($i=0; $i<count($char); $i+=CharacterPieces())
  {
    $cardAspects = explode(",", CardAspects($char[$i]));
    for($j=0; $j<count($cardAspects); ++$j) {
      ++$aspects[$cardAspects[$j]];
    }
  }
  return $aspects;
}

function IsAlternativeCostPaid($cardID, $from)
{
  global $currentTurnEffects, $currentPlayer;
  $isAlternativeCostPaid = false;
  for($i = count($currentTurnEffects) - CurrentTurnPieces(); $i >= 0; $i -= CurrentTurnPieces()) {
    $remove = false;
    if($currentTurnEffects[$i + 1] == $currentPlayer) {
      switch($currentTurnEffects[$i]) {
        case "ARC185": case "CRU188": case "MON199": case "MON257": case "EVR161":
          $isAlternativeCostPaid = true;
          $remove = true;
          break;
        default:
          break;
      }
      if($remove) RemoveCurrentTurnEffect($i);
    }
  }
  return $isAlternativeCostPaid;
}

function BanishCostModifier($from, $index)
{
  global $currentPlayer;
  if($from != "BANISH") return 0;
  $banish = GetBanish($currentPlayer);
  $mod = explode("-", $banish[$index + 1]);
  switch($mod[0]) {
    case "ARC119": return -1 * intval($mod[1]);
    default: return 0;
  }
}

function IsCurrentAttackName($name)
{
  $names = GetCurrentAttackNames();
  for($i=0; $i<count($names); ++$i)
  {
    if($name == $names[$i]) return true;
  }
  return false;
}

function IsCardNamed($player, $cardID, $name)
{
  global $currentTurnEffects;
  if(CardName($cardID) == $name) return true;
  for($i=0; $i<count($currentTurnEffects); $i+=CurrentTurnEffectPieces())
  {
    $effectArr = explode("-", $currentTurnEffects[$i]);
    $name = CurrentEffectNameModifier($effectArr[0], (count($effectArr) > 1 ? GamestateUnsanitize($effectArr[1]) : "N/A"));
    //You have to do this at the end, or you might have a recursive loop -- e.g. with OUT052
    if($name != "" && $currentTurnEffects[$i+1] == $player) return true;
  }
  return false;
}

function GetCurrentAttackNames()
{
  global $combatChain, $currentTurnEffects, $mainPlayer;
  $names = [];
  if(count($combatChain) == 0) return $names;
  array_push($names, CardName($combatChain[0]));
  for($i=0; $i<count($currentTurnEffects); $i+=CurrentTurnEffectPieces())
  {
    $effectArr = explode("-", $currentTurnEffects[$i]);
    $name = CurrentEffectNameModifier($effectArr[0], (count($effectArr) > 1 ? GamestateUnsanitize($effectArr[1]) : "N/A"));
    //You have to do this at the end, or you might have a recursive loop -- e.g. with OUT052
    if($name != "" && $currentTurnEffects[$i+1] == $mainPlayer && IsCombatEffectActive($effectArr[0]) && !IsCombatEffectLimited($i)) array_push($names, $name);
  }
  return $names;
}

function SerializeCurrentAttackNames()
{
  $names = GetCurrentAttackNames();
  $serializedNames = "";
  for($i=0; $i<count($names); ++$i)
  {
    if($serializedNames != "") $serializedNames .= ",";
    $serializedNames .= GamestateSanitize($names[$i]);
  }
  return $serializedNames;
}

function HasAttackName($name)
{
  global $chainLinkSummary;
  for($i=0; $i<count($chainLinkSummary); $i+=ChainLinkSummaryPieces())
  {
    $names = explode(",", $chainLinkSummary[$i+4]);
    for($j=0; $j<count($names); ++$j)
    {
      if($name == GamestateUnsanitize($names[$j])) return true;
    }
  }
  return false;
}

function HasPlayedAttackReaction()
{
  global $combatChain, $mainPlayer;
  for($i=CombatChainPieces(); $i<count($combatChain); $i+=CombatChainPieces())
  {
    if($combatChain[$i+1] != $mainPlayer) continue;
    if(CardType($combatChain[$i]) == "AR" || GetResolvedAbilityType($combatChain[$i])) return true;
  }
  return false;
}

function HitEffectsArePrevented()
{
  global $combatChainState, $CCS_ChainLinkHitEffectsPrevented;
  return $combatChainState[$CCS_ChainLinkHitEffectsPrevented];
}

function HitEffectsPreventedThisLink()
{
  global $combatChainState, $CCS_ChainLinkHitEffectsPrevented;
  $combatChainState[$CCS_ChainLinkHitEffectsPrevented] = 1;
}

function EffectPreventsHit()
{
  global $currentTurnEffects, $mainPlayer, $combatChain;
  $preventsHit = false;
  for($i=count($currentTurnEffects)-CurrentTurnPieces(); $i >= 0; $i-=CurrentTurnPieces())
  {
    if($currentTurnEffects[$i+1] != $mainPlayer) continue;
    $remove = 0;
    switch($currentTurnEffects[$i])
    {
      case "OUT108": if(CardType($combatChain[0]) == "AA") { $preventsHit = true; $remove = 1; } break;
      default: break;
    }
    if($remove == 1) RemoveCurrentTurnEffect($i);
  }
  return $preventsHit;
}

function HitsInRow()
{
  global $chainLinkSummary;
  $numHits = 0;
  for($i=count($chainLinkSummary)-ChainLinkSummaryPieces(); $i>=0 && intval($chainLinkSummary[$i+5]) > 0; $i-=ChainLinkSummaryPieces())
  {
    ++$numHits;
  }
  return $numHits;
}

function HitsInCombatChain()
{
  global $chainLinkSummary, $combatChainState, $CCS_HitThisLink;
  $numHits = intval($combatChainState[$CCS_HitThisLink]);
  for($i=count($chainLinkSummary)-ChainLinkSummaryPieces(); $i>=0; $i-=ChainLinkSummaryPieces())
  {
    $numHits += intval($chainLinkSummary[$i+5]);
  }
  return $numHits;
}

function NumAttacksHit()
{
    global $chainLinkSummary;
    $numHits = 0;
    for($i=count($chainLinkSummary)-ChainLinkSummaryPieces(); $i>=0; $i-=ChainLinkSummaryPieces())
    {
      if($chainLinkSummary[$i] > 0) ++$numHits;
    }
    return $numHits;
}

function NumChainLinks()
{
  global $chainLinkSummary, $combatChain;
  $numLinks = count($chainLinkSummary)/ChainLinkSummaryPieces();
  if(count($combatChain) > 0) ++$numLinks;
  return $numLinks;
}

function ClearGameFiles($gameName)
{
  unlink("./Games/" . $gameName . "/gamestateBackup.txt");
  unlink("./Games/" . $gameName . "/beginTurnGamestate.txt");
  unlink("./Games/" . $gameName . "/lastTurnGamestate.txt");
}

function IsClassBonusActive($player, $class)
{
  $char = &GetPlayerCharacter($player);
  if(count($char) == 0) return false;
  if(ClassContains($char[0], $class, $player)) return true;
  return false;
}

function PlayAbility($cardID, $from, $resourcesPaid, $target = "-", $additionalCosts = "-")
{
  global $currentPlayer, $layers, $CS_NumAttacks, $CS_PlayIndex;
  $index = GetClassState($currentPlayer, $CS_PlayIndex);
  if($target != "-")
  {
    $targetArr = explode("-", $target);
    if($targetArr[0] == "LAYERUID") { $targetArr[0] = "LAYER"; $targetArr[1] = SearchLayersForUniqueID($targetArr[1]); }
    $target = $targetArr[0] . "-" . $targetArr[1];
  }
  if($from != "PLAY" && IsAlly($cardID, $currentPlayer)) {
    $playAlly = new Ally("MYALLY-" . LastAllyIndex($currentPlayer));
    if(HasShielded($cardID, $currentPlayer, $playAlly->Index())) $playAlly->Attach("8752877738");//Shield Token
  }
  switch($cardID)
  {
    case "4721628683"://Patrolling V-Wing
      if($from != "PLAY") Draw($currentPlayer);
      break;
    case "2050990622"://Spark of Rebellion
      AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "THEIRHAND");
      AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose which card you want your opponent to discard", 1);
      AddDecisionQueue("CHOOSEMULTIZONE", $currentPlayer, "<-", 1);
      AddDecisionQueue("MZDISCARD", $currentPlayer, "HAND," . $currentPlayer, 1);
      AddDecisionQueue("MZREMOVE", $currentPlayer, "-", 1);
      break;
    case "3377409249"://Rogue Squadron Skirmisher
      if($from != "PLAY") MZMoveCard($currentPlayer, "MYDISCARD:maxCost=2", "MYHAND", may:true);
      break;
    case "5335160564":
      if($from != "PLAY" && (GetHealth(1) >= 15 || GetHealth(2) >= 15)) {
        $ally = new Ally("MYALLY-" . $index);
        $ally->Ready();
      }
      break;
    case "7262314209"://Mission Briefing
      Draw($currentPlayer);
      Draw($currentPlayer);
      break;
    case "6253392993"://Bright Hope
      if($from != "PLAY") {
        MZMoveCard($currentPlayer, "MYALLY:arena=Ground", "MYHAND", may:true);
        AddDecisionQueue("DRAW", $currentPlayer, "-", 1);
      }
      break;
    case "6702266551"://Smoke and Cinders
      $hand = &GetHand(1);
      for($i=0; $i<(count($hand)/HandPieces())-2; ++$i) PummelHit(1);
      $hand = &GetHand(2);
      for($i=0; $i<(count($hand)/HandPieces())-2; ++$i) PummelHit(2);
      break;
    case "8148673131"://Open Fire
      DealArcane(ArcaneDamage($cardID), 1, "PLAYCARD", $cardID, resolvedTarget: $target);
      break;
    case "8429598559"://Black One
      if($from != "PLAY") BlackOne($currentPlayer);
      break;
    case "8986035098"://Viper Probe Droid
      if($from != "PLAY") LookAtHand($currentPlayer == 1 ? 2 : 1);
      break;
    case "9266336818"://Grand Moff Tarkin
      if($from != "PLAY") {
        AddDecisionQueue("FINDINDICES", $currentPlayer, "DECKTOPXREMOVE," . 5);
        AddDecisionQueue("SETDQVAR", $currentPlayer, "0", 1);
        AddDecisionQueue("FILTER", $currentPlayer, "LastResult-include-trait-Imperial", 1);
        AddDecisionQueue("CHOOSECARD", $currentPlayer, "<-", 1);
        AddDecisionQueue("ADDHAND", $currentPlayer, "-", 1);
        AddDecisionQueue("OP", $currentPlayer, "REMOVECARD");
        AddDecisionQueue("SETDQVAR", $currentPlayer, "0", 1);
        AddDecisionQueue("FILTER", $currentPlayer, "LastResult-include-trait-Imperial", 1);
        AddDecisionQueue("CHOOSECARD", $currentPlayer, "<-", 1);
        AddDecisionQueue("ADDHAND", $currentPlayer, "-", 1);
        AddDecisionQueue("OP", $currentPlayer, "REMOVECARD");
        AddDecisionQueue("CHOOSEBOTTOM", $currentPlayer, "<-");
      }
      break;
    case "9459170449"://Cargo Juggernaut
      if($from != "PLAY" && SearchCount(SearchAllies($currentPlayer, aspect:"Vigilance")) > 0) {
        Restore(4, $currentPlayer);
      }
      break;
    case "7257556541"://Bodhi Rook
      if($from != "PLAY") {
        $otherPlayer = $currentPlayer == 1 ? 2 : 1;
        AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose an opponent card to discard");
        AddDecisionQueue("FINDINDICES", $otherPlayer, "HAND");
        AddDecisionQueue("CHOOSETHEIRHAND", $currentPlayer, "<-", 1);
        AddDecisionQueue("MULTIREMOVEHAND", $otherPlayer, "-", 1);
        AddDecisionQueue("ADDDISCARD", $otherPlayer, "HAND", 1);
      }
      break;
    case "6028207223"://Pirated Starfighter
      if($from != "PLAY") {
        AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "MYALLY");
        AddDecisionQueue("CHOOSEMULTIZONE", $currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $currentPlayer, "BOUNCE", 1);
      }
      break;
    case "8981523525"://Moment of Peace
      $ally = new Ally($target);
      $ally->Attach("8752877738");//Shield
      break;
    case "8679831560"://Repair
      $mzArr = explode("-", $target);
      if($mzArr[0] == "MYCHAR") Restore(3, $currentPlayer);
      else if($mzArr[0] == "MYALLY") {
        $ally = new Ally($target);
        $ally->Heal(3);
      }
      break;
    case "7533529264"://Wolffe
      $otherPlayer = $currentPlayer == 1 ? 2 : 1;
      AddCurrentTurnEffect($cardID, $currentPlayer);
      AddCurrentTurnEffect($cardID, $otherPlayer);
      break;
    case "7596515127"://Academy Walker
      if($from != "PLAY") {
        $allies = &GetAllies($currentPlayer);
        for($i=0; $i<count($allies); $i+=AllyPieces()) {
          $ally = new Ally("MYALLY-" . $i);
          if($ally->IsDamaged()) $ally->Attach("2007868442");//Experience token
        }
      }
      break;
    case "7202133736"://Waylay
      if($target != "-") {
        AddDecisionQueue("PASSPARAMETER", $currentPlayer, $target);
        AddDecisionQueue("MZOP", $currentPlayer, "BOUNCE", 1);
      }
      break;
    case "7485151088"://Search your feelings
      AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "MYDECK");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
      AddDecisionQueue("MZREMOVE", $currentPlayer, "-", 1);
      AddDecisionQueue("MULTIADDHAND", $currentPlayer, "-", 1);
      AddDecisionQueue("SHUFFLEDECK", $currentPlayer, "-");
      break;
    case "0176921487"://Power of the Dark Side
      $otherPlayer = $currentPlayer == 1 ? 2 : 1;
      MZChooseAndDestroy($otherPlayer, "MYALLY");
      break;
    case "0827076106"://Admiral Ackbar
      if($from != "PLAY") {
        $targetCard = GetMZCard($currentPlayer, $target);
        $damage = SearchCount(SearchAllies($currentPlayer, arena:CardArenas($targetCard)));
        DealArcane($damage, 1, "PLAYCARD", $cardID, resolvedTarget: $target);
      }
      break;
    case "0867878280"://It Binds All Things
      $ally = new Ally($target);
      $ally->Heal(3);
      if(HasLeader($currentPlayer)) {
        DealArcane($damage, 2, "PLAYCARD", $cardID);
      }
      break;
    case "1021495802"://Cantina Bouncer
      if($from != "PLAY") {
        AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "THEIRALLY");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $currentPlayer, "BOUNCE", 1);
      }
      break;
    case "1353201082"://Superlaser Blast
      DestroyAllAllies();
      break;
    case "1705806419"://Force Throw
      $otherPlayer = $currentPlayer == 1 ? 2 : 1;
      PummelHit($otherPlayer);
      if(SearchCount(SearchAllies($currentPlayer, trait:"Force")) > 0) {
        AddDecisionQueue("SPECIFICCARD", $currentPlayer, "FORCETHROW", 1);
      }
      break;
    case "1746195484"://Jedha Agitator
      if($from == "PLAY" && HasLeader($currentPlayer)) DealArcane(2, 2, "PLAYCARD", $cardID); 
      break;
    case "2587711125"://Disarm
      $ally = new Ally($target);
      AddDecisionQueue("PASSPARAMETER", $currentPlayer, $ally->UniqueID());
      AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $ally->PlayerID(), "2587711125,HAND");
      break;
    case "5707383130"://Bendu
      if($from == "PLAY") {
        AddCurrentTurnEffect($cardID, $currentPlayer);
      }
      break;
    case "6472095064"://Vanquish
      MZChooseAndDestroy($currentPlayer, "THEIRALLY");
      break;
    case "6663619377"://AT-AT Suppressor
      ExhaustAllAllies("Ground", 1);
      ExhaustAllAllies("Ground", 2);
      break;
    case "6931439330"://The Ghost
      AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a unit to give a shield");
      AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "MYALLY:trait=Spectre");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $currentPlayer, "ADDSHIELD", 1);
      break;
    case "8691800148"://Reinforcement Walker
      AddDecisionQueue("FINDINDICES", $currentPlayer, "TOPDECK");
      AddDecisionQueue("DECKCARDS", $currentPlayer, "<-", 1);
      AddDecisionQueue("REVEALCARDS", $currentPlayer, "-", 1);
      AddDecisionQueue("YESNO", $currentPlayer, "if you want to draw", 1);
      AddDecisionQueue("SPECIFICCARD", $currentPlayer, "REINFORCEMENTWALKER", 1);
      break;
    case "9002021213"://Imperial Interceptor
      if($from != "PLAY") {
        DealArcane(3, 2, "PLAYCARD", $cardID);
      }
      break;
    case "9133080458"://Inferno Four
      if($from != "PLAY") PlayerOpt($currentPlayer, 2);
      break;
    case "9568000754"://R2-D2
      PlayerOpt($currentPlayer, 1);
      break;
    case "9624333142"://Count Dooku
      if($from != "PLAY") {
        MZChooseAndDestroy($currentPlayer, "MYALLY&THEIRALLY", may:true);
      }
      break;
    case "9097316363":
      if($from != "PLAY") {
        for($i=0; $i<6; ++$i) DealArcane(1, 2, "PLAYCARD", $cardID);
      }
      break;
    case "0256267292":
      if($from == "PLAY") {
        AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a card to give Raid 2");
        AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "MYALLY:aspect=Aggression");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $currentPlayer, "GETUNIQUEID", 1);
        AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $currentPlayer, "0256267292,HAND", 1);
      }
      break;
    case "1208707254"://Rallying Cry
      AddCurrentTurnEffect($cardID, $currentPlayer);
      break;
    case "1446471743"://Force Choke
      DealArcane(5, 2, "PLAYCARD", $cardID);
      $otherPlayer = $currentPlayer == 1 ? 2 : 1;
      Draw($otherPlayer);
      break;
    case "1047592361"://Ruthless Raider
      if($from != "PLAY") {
        DealArcane(2, 1, "PLAYCARD", $cardID);
        DealArcane(2, 2, "PLAYCARD", $cardID);
      }
      break;
    case "1862616109"://Snowspeeder
      if($from == "PLAY") {
        AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a card to exhaust");
        AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "THEIRALLY:trait=Vehicle");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $currentPlayer, "REST", 1);
      }
      break;
    case "2554951775"://Bail Organa
      if($from == "PLAY" && GetResolvedAbilityType($cardID) == "A") {
        AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a unit to add an experience");
        AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "MYALLY");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $currentPlayer, "ADDEXPERIENCE", 1);
      }
      break;
    case "3058784025"://Keep Fighting
      AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a unit to ready");
      AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "MYALLY:maxAttack=3&THEIRALLY:maxAttack=3");
      AddDecisionQueue("CHOOSEMULTIZONE", $currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $currentPlayer, "READY", 1);
      break;
    case "3613174521"://Outer Rim Headhunter
      if($from == "PLAY" && HasLeader($currentPlayer)) {
        AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a card to exhaust");
        AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "THEIRALLY");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $currentPlayer, "REST", 1);
      }
      break;
    case "3684950815"://Bounty Hunter Crew
      if($from != "PLAY") MZMoveCard($currentPlayer, "MYDISCARD:definedType=Event", "MYHAND", may:true);
      break;
    case "4092697474"://TIE Advanced
      if($from != "PLAY") {
        AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a card to give experience");
        AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "MYALLY:trait=Imperial");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $currentPlayer, "ADDEXPERIENCE", 1);
        AddDecisionQueue("MZOP", $currentPlayer, "ADDEXPERIENCE", 1);
      }
      break;
    case "4536594859"://Medal Ceremony
      WriteLog("Make sure you manually enforce the restriction for Medal Ceremony");
      for($i=0; $i<3; ++$i) {
        AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a card to give experience");
        AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "MYALLY");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $currentPlayer, "ADDEXPERIENCE", 1);
      }
      break;
    case "5449704164"://2-1B Surgical Droid
      if($from == "PLAY") {
        AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a card to heal");
        AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "MYALLY");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $currentPlayer, "HEALALLY,2", 1);
      }
      break;
    case "6515891401"://Karabast
      $ally = new Ally($target);
      $damage = $ally->MaxHealth() - $ally->Health() + 1;
      DealArcane($damage, 2, "PLAYCARD", $ally->CardID());
      break;
    case "7929181061"://General Tagge
      if($from != "PLAY") {
        WriteLog("Make sure you manually enforce limit 1 per unit");
        for($i=0; $i<3; ++$i) {
          AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a card to give experience");
          AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "MYALLY:trait=Trooper");
          AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
          AddDecisionQueue("MZOP", $currentPlayer, "ADDEXPERIENCE", 1);
        }
      }
      break;
    case "8240629990"://Avenger
      MZChooseAndDestroy($otherPlayer, "MYALLY");
      break;
    case "8294130780"://Gladiator Star Destroyer
      if($from != "PLAY") {
        AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a card to give Sentinel");
        AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "MYALLY");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $currentPlayer, "GETUNIQUEID", 1);
        AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $currentPlayer, "8294130780,HAND", 1);
      }
      break;
    case "4919000710"://Home One
      if($from != "PLAY") {
        AddCurrentTurnEffect($cardID, $currentPlayer);//Cost discount
        AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a card to play");
        AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "MYDISCARD:Aspect=Heroism");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $currentPlayer, "PLAYCARD", 1);
      }
      break;
    case "4849184191"://Take Down
      MZChooseAndDestroy($currentPlayer, "THEIRALLY");
      break;
    case "4631297392"://Devastator
      if($from != "PLAY") {
        $resourceCards = &GetResourceCards($currentPlayer);
        $numResources = count($resourceCards)/ResourcePieces();
        DealArcane($numResources, 2, "PLAYCARD", "4631297392");
      }
      break;
    case "4599464590"://Rugged Survivors
      if($from == "PLAY" && HasLeader($currentPlayer)) {
        Draw($currentPlayer);
      }
      break;
    case "4299027717"://Mining Guild Tie Fighter
      if($from == "PLAY") {
        AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Do you want to pay 2 to draw a card?");
        AddDecisionQueue("YESNO", $currentPlayer, "-", 0, 1);
        AddDecisionQueue("NOPASS", $currentPlayer, "", 1);
        AddDecisionQueue("PAYRESOURCES", $currentPlayer, "2", 1);
        AddDecisionQueue("DRAW", $currentPlayer, "-", 1);
      }
      break;
    case "3802299538"://Cartel Spacer
      if($from != "PLAY" && SearchCount(SearchAllies($currentPlayer, aspect:"Cunning")) > 1) {
        AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a card to exhaust");
        AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "THEIRALLY:maxCost=4");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $currentPlayer, "REST", 1);
      }
      break;
    case "3443737404"://Wing Leader
      if($from != "PLAY") {
        AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a unit to add experience");
        AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "MYALLY:trait=Rebel");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $currentPlayer, "ADDEXPERIENCE", 1);
        AddDecisionQueue("MZOP", $currentPlayer, "ADDEXPERIENCE", 1);
      }
      break;
    case "2756312994"://Alliance Dispatcher
      if($from == "PLAY" && GetResolvedAbilityType($cardID) == "A") {
        AddCurrentTurnEffect($cardID, $currentPlayer);//Cost discount
        AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a card to play");
        AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "MYHAND:definedType=Unit");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $currentPlayer, "PLAYCARD", 1);
      }
      break;
    case "2569134232"://Jedha City
      $ally = new Ally($target);
      AddDecisionQueue("PASSPARAMETER", $currentPlayer, $ally->UniqueID());
      AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $ally->PlayerID(), "2569134232,HAND");
      break;
    case "1349057156"://Strike True
      $ally = new Ally($target);
      $damage = $ally->CurrentPower();
      DealArcane($damage, 2, "PLAYCARD", $ally->CardID());
      break;
    case "1393827469"://Tarkin Town
      DealArcane(3, 2, "PLAYCARD", "1393827469");
      break;
    case "1880931426"://Lothal Insurgent
      global $CS_NumCardsPlayed;
      if($from != "PLAY" && GetClassState($currentPlayer, $CS_NumCardsPlayed) > 1) {
        $otherPlayer = $currentPlayer == 1 ? 2 : 1;
        Draw($otherPlayer);
        DiscardRandom($otherPlayer, $cardID);
      }
      break;
    case "2429341052"://Security Complex
      AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a unit to give a shield");
      AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "MYALLY");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $currentPlayer, "ADDSHIELD", 1);
      break;
    case "3018017739"://Vanguard Ace
      global $CS_NumCardsPlayed;
      if($from != "PLAY") {
        $ally = new Ally("MYALLY-" . LastAllyIndex($currentPlayer));
        for($i=0; $i<GetClassState($currentPlayer, $CS_NumCardsPlayed); ++$i) {
          $ally->Attach("2007868442");//Experience token
        }
      }
      break;
    case "3401690666"://Relentless
      if($from != "PLAY") {
        $otherPlayer = ($currentPlayer == 1 ? 2 : 1);
        AddCurrentTurnEffect("3401690666", $otherPlayer, from:"PLAY");
      }
      break;
    case "3407775126"://Recruit
      AddDecisionQueue("FINDINDICES", $currentPlayer, "DECKTOPXREMOVE," . 5);
      AddDecisionQueue("SETDQVAR", $currentPlayer, "0", 1);
      AddDecisionQueue("FILTER", $currentPlayer, "LastResult-include-definedType-Unit", 1);
      AddDecisionQueue("CHOOSECARD", $currentPlayer, "<-", 1);
      AddDecisionQueue("ADDHAND", $currentPlayer, "-", 1);
      AddDecisionQueue("OP", $currentPlayer, "REMOVECARD");
      AddDecisionQueue("CHOOSEBOTTOM", $currentPlayer, "<-");
      break;
    case "3498814896"://Mon Mothma
      if($from != "PLAY") {
        AddDecisionQueue("FINDINDICES", $currentPlayer, "DECKTOPXREMOVE," . 5);
        AddDecisionQueue("SETDQVAR", $currentPlayer, "0", 1);
        AddDecisionQueue("FILTER", $currentPlayer, "LastResult-include-trait-Rebel", 1);
        AddDecisionQueue("CHOOSECARD", $currentPlayer, "<-", 1);
        AddDecisionQueue("ADDHAND", $currentPlayer, "-", 1);
        AddDecisionQueue("OP", $currentPlayer, "REMOVECARD");
        AddDecisionQueue("CHOOSEBOTTOM", $currentPlayer, "<-");
      }
      break;
    case "3509161777"://You're My Only Hope
      $deck = new Deck($currentPlayer);
      AddDecisionQueue("PASSPARAMETER", $currentPlayer, $deck->Top());
      AddDecisionQueue("SETDQVAR", $currentPlayer, 0);
      AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Do you want to play <0>?");
      AddDecisionQueue("YESNO", $currentPlayer, "-");
      AddDecisionQueue("NOPASS", $currentPlayer, "-");
      AddDecisionQueue("ADDCURRENTEFFECT", $currentPlayer, "3509161777", 1);
      AddDecisionQueue("PASSPARAMETER", $currentPlayer, "MYDECK-0", 1);
      AddDecisionQueue("MZOP", $currentPlayer, "PLAYCARD", 1);
      break;
    case "3572356139"://Chewbacca, Walking Carpet
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Play Taunt") {
        global $CS_AfterPlayedBy;
        SetClassState($currentPlayer, $CS_AfterPlayedBy, $cardID);
        AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a card to put into play");
        AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "MYHAND:definedType=Unit&maxCost=3");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $currentPlayer, "PLAYCARD", 1);
      } else if($abilityName == "Deploy") {
        PlayAlly("8301e8d7ef", $currentPlayer);
      }
      break;
    case "2579145458"://Luke Skywalker
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Give Shield") {
        AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "MYALLY");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $currentPlayer, "ADDSHIELD", 1);
      } else if($abilityName == "Deploy") {
        PlayAlly("0dcb77795c", $currentPlayer);
      }
      break;
    case "2912358777"://Grand Moff Tarkin
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Give Experience") {
        AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "MYALLY:trait=Imperial");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $currentPlayer, "ADDEXPERIENCE", 1);
      } else if($abilityName == "Deploy") {
        PlayAlly("59cd013a2d", $currentPlayer);
      }
      break;
    case "3187874229"://Cassian Andor"
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Draw Card") {
        global $CS_DamageTaken;
        $otherPlayer = $currentPlayer == 1 ? 2 : 1;
        if(GetClassState($otherPlayer, $CS_DamageTaken) >= 3) Draw($currentPlayer);
      } else if($abilityName == "Deploy") {
        PlayAlly("3c60596a7a", $currentPlayer);
      }
      break;
    case "4841169874"://Sabine Wren
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Deal Damage") {
        DealArcane(1, 1, "PLAYCARD", "4841169874");
        DealArcane(1, 4, "PLAYCARD", "4841169874");
      } else if($abilityName == "Deploy") {
        PlayAlly("51e8757e4c", $currentPlayer);
      }
      break;
    case "5871074103"://Forced Surrender
      Draw($currentPlayer);
      Draw($currentPlayer);
      global $CS_DamageTaken;
      $otherPlayer = $currentPlayer == 1 ? 2 : 1;
      if(GetClassState($otherPlayer, $CS_DamageTaken) > 0) {
        PummelHit($otherPlayer);
        PummelHit($otherPlayer);
      }
      break;
    case "9250443409"://Lando Calrissian
      if($from != "PLAY") {
        for($i=0; $i<2; ++$i) {
          AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose up to two resource cards to return to your hand");
          AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "MYRESOURCES");
          AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
          AddDecisionQueue("MZOP", $currentPlayer, "BOUNCE", 1);
        }
      }
      break;
    case "9070397522"://Rebel Trooper
      if($from != "PLAY") {
        AddCurrentTurnEffect($cardID, $currentPlayer);
      }
      break;
    case "8560666697"://Director Krennig
      PlayAlly("e2c6231b35", $currentPlayer);
      break;
    case "6458912354"://Death Trooper
      if($from != "PLAY") {
        AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a unit to deal 2 damage to");
        AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "MYALLY:arena=Ground");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $currentPlayer, "DEALDAMAGE,2", 1);
        AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a unit to deal 2 damage to");
        AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "THEIRALLY:arena=Ground");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $currentPlayer, "DEALDAMAGE,2", 1);
      }
      break;
    case "7109944284"://Luke Skywalker
      global $CS_NumAlliesDestroyed;
      if($from != "PLAY") {
        $amount = GetClassState($CS_NumAlliesDestroyed) > 0 ? 6 : 3;
        AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a unit to debuff");
        AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "THEIRALLY");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $currentPlayer, "DEALDAMAGE," . $amount, 1);
      }
      break;
    case "7366340487"://Outmaneuver
      AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a mode for Outmaneuver");
      AddDecisionQueue("MULTICHOOSETEXT", $currentPlayer, "1-Ground,Space-1");
      AddDecisionQueue("SHOWMODES", $currentPlayer, $cardID, 1);
      AddDecisionQueue("MODAL", $currentPlayer, "OUTMANEUVER", 1);
      break;
    case "6901817734"://Asteroid Sanctuary
      AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a unit to exhaust");
      AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "THEIRALLY");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $currentPlayer, "REST", 1);
      AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a unit to give a shield token");
      AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "MYALLY:maxCost=3");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $currentPlayer, "ADDSHIELD", 1);
      break;
    case "7440067052"://Hera Sykulla
      PlayAlly("80df3928eb", $currentPlayer);
      break;
    case "0705773109"://Vader's Lightsaber
      if(CardTitle(GetMZCard($currentPlayer, $target)) == "Darth Vader") {
        AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a unit to deal 4 damage to");
        AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "THEIRALLY:arena=Ground");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $currentPlayer, "DEALDAMAGE,4", 1);
      }
      break;
    case "2048866729"://Iden Versio
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Heal") {
        global $CS_NumAlliesDestroyed;
        $otherPlayer = $currentPlayer == 1 ? 2 : 1;
        if(GetClassState($otherPlayer, $CS_NumAlliesDestroyed) > 0) {
          Restore(1, $currentPlayer);
        }
      } else if($abilityName == "Deploy") {
        PlayAlly("b0dbca5c05", $currentPlayer);
      }
      break;
    case "9680213078"://Leia Organa
      if($from != "PLAY") {
        AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a mode for Leia Organa");
        AddDecisionQueue("MULTICHOOSETEXT", $currentPlayer, "1-Ready Resource,Exhaust Unit-1");
        AddDecisionQueue("SHOWMODES", $currentPlayer, $cardID, 1);
        AddDecisionQueue("MODAL", $currentPlayer, "LEIAORGANA", 1);
      }
      break;
    case "7916724925"://Bombing Run
      AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a mode for Bombing Run");
      AddDecisionQueue("MULTICHOOSETEXT", $currentPlayer, "1-Ground,Space-1");
      AddDecisionQueue("SHOWMODES", $currentPlayer, $cardID, 1);
      AddDecisionQueue("MODAL", $currentPlayer, "BOMBINGRUN", 1);
      break;
    case "6088773439"://Darth Vader
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Deal Damage") {
        AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a unit to deal 1 damage to");
        AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "MYALLY&THEIRALLY");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $currentPlayer, "DEALDAMAGE,1", 1);
        DealArcane(1, 1, "PLAYCARD", "6088773439");
      } else if($abilityName == "Deploy") {
        PlayAlly("0ca1902a46", $currentPlayer);
      }
      break;
    case "3503494534"://Regional Governor
      if($from != "PLAY") {
        WriteLog("This is a partially manual card. Name the card in chat and enforce the restrictions.");
      }
      break;
    case "0523973552"://I Am Your Father
      $otherPlayer = $currentPlayer == 1 ? 2 : 1;
      AddDecisionQueue("SETDQCONTEXT", $otherPlayer, "Do you want your opponent to deal 7 damage?");
      AddDecisionQueue("YESNO", $otherPlayer, "-");
      AddDecisionQueue("NOPASS", $otherPlayer, "-", 1);
      AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a unit to deal 7 damage to", 1);
      AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "THEIRALLY", 1);
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $currentPlayer, "DEALDAMAGE,7", 1);
      AddDecisionQueue("ELSE", $otherPlayer, "-");
      AddDecisionQueue("DRAW", $currentPlayer, "-", 1);
      AddDecisionQueue("DRAW", $currentPlayer, "-", 1);
      AddDecisionQueue("DRAW", $currentPlayer, "-", 1);
      break;
    case "6903722220"://Luke's Lightsaber
      if(CardTitle(GetMZCard($currentPlayer, $target)) == "Luke Skywalker") {
        $ally = new Ally(GetMZCard($currentPlayer, $target), $currentPlayer);
        $ally->Heal($ally->MaxHealth()-$ally->Health());
        $ally->Attach("8752877738");//Shield Token
      }
      break;
    case "5494760041"://Galactic Ambition
      global $CS_AfterPlayedBy;
      SetClassState($currentPlayer, $CS_AfterPlayedBy, $cardID);
      AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a card to play");
      AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "MYHAND");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
      AddDecisionQueue("SETDQVAR", $currentPlayer, 0, 1);
      AddDecisionQueue("ADDCURRENTEFFECT", $currentPlayer, "5494760041", 1);
      AddDecisionQueue("PASSPARAMETER", $currentPlayer, "{0}", 1);
      AddDecisionQueue("MZOP", $currentPlayer, "GETCARDID", 1);
      AddDecisionQueue("SETDQVAR", $currentPlayer, 1, 1);
      AddDecisionQueue("PASSPARAMETER", $currentPlayer, "{0}", 1);
      AddDecisionQueue("MZOP", $currentPlayer, "PLAYCARD", 1);
      break;
    case "2651321164"://Tactical Advantage
      $ally = new Ally($target);
      $ally->AddHealth(2);
      AddCurrentTurnEffect($cardID, $currentPlayer, "PLAY", $ally->UniqueID());
      break;
    case "1900571801"://Overwhelming Barrage
      $ally = new Ally($target);
      $ally->AddHealth(2);
      AddCurrentTurnEffect($cardID, $currentPlayer, "PLAY", $ally->UniqueID());
      for($i=0; $i<$ally->CurrentPower(); ++$i) {
        AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a unit to deal 1 damage to");
        AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "MYALLY&THEIRALLY");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $currentPlayer, "DEALDAMAGE,1", 1);
      }
      break;
    case "3974134277"://Prepare for Takeoff
      AddDecisionQueue("FINDINDICES", $currentPlayer, "DECKTOPXREMOVE," . 5);
      AddDecisionQueue("SETDQVAR", $currentPlayer, "0", 1);
      AddDecisionQueue("FILTER", $currentPlayer, "LastResult-include-trait-Vehicle", 1);
      AddDecisionQueue("CHOOSECARD", $currentPlayer, "<-", 1);
      AddDecisionQueue("ADDHAND", $currentPlayer, "-", 1);
      AddDecisionQueue("OP", $currentPlayer, "REMOVECARD");
      AddDecisionQueue("SETDQVAR", $currentPlayer, "0", 1);
      AddDecisionQueue("FILTER", $currentPlayer, "LastResult-include-trait-Vehicle", 1);
      AddDecisionQueue("CHOOSECARD", $currentPlayer, "<-", 1);
      AddDecisionQueue("ADDHAND", $currentPlayer, "-", 1);
      AddDecisionQueue("OP", $currentPlayer, "REMOVECARD");
      AddDecisionQueue("CHOOSEBOTTOM", $currentPlayer, "<-");
      break;
    case "3896582249"://Redemption
      $ally = new Ally("MYALLY-" . LastAllyIndex($currentPlayer));
      for($i=0; $i<8; ++$i) {
        AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a card to restore 1", $i == 0 ? 0 : 1);
        AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "MYCHAR&MYALLY", $i == 0 ? 0 : 1);
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $currentPlayer, "RESTORE,1", 1);
        AddDecisionQueue("PASSPARAMETER", $currentPlayer, "MYALLY-" . LastAllyIndex($currentPlayer), 1);
        AddDecisionQueue("MZOP", $currentPlayer, "DEALDAMAGE,1", 1);
      }
      break;
    case "7861932582"://The Force is With Me
      $ally = new Ally($target, $currentPlayer);
      $ally->Attach("2007868442");//Experience token
      $ally->Attach("2007868442");//Experience token
      if(TraitContains($ally->CardID(), "Force", $currentPlayer)) {
        $ally->Attach("8752877738");//Shield Token
      }
      WriteLog("This is a partially manual card. Do the extra attack by passing priority manually.");
      break;
    case "9985638644"://Snapshot Reflexes
      WriteLog("This is a partially manual card. Do the extra attack by passing priority manually.");
      break;
    case "7728042035"://Chimaera
      if($from != "PLAY") {
        AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Name the card in chat");
        AddDecisionQueue("OK", $currentPlayer, "-");
        AddDecisionQueue("PASSPARAMETER", $currentPlayer, "1");
        $otherPlayer = $currentPlayer == 1 ? 2 : 1;
        AddDecisionQueue("FINDINDICES", $otherPlayer, "HAND");
        AddDecisionQueue("SETDQCONTEXT", $otherPlayer, "Choose a card to discard", 1);
        AddDecisionQueue("MAYCHOOSEHAND", $otherPlayer, "<-", 1);
        AddDecisionQueue("MULTIREMOVEHAND", $otherPlayer, "-", 1);
        AddDecisionQueue("ADDDISCARD", $otherPlayer, "HAND", 1);
      }
      break;
    case "3809048641"://Surprise Strike
      WriteLog("This is a partially manual card. Do the extra attack by passing priority manually.");
      AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a card to attack and give +3");
      AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "MYALLY");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $currentPlayer, "GETUNIQUEID", 1);
      AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $currentPlayer, "3809048641,HAND", 1);
      break;
    case "3038238423"://Fleet Lieutenant
      WriteLog("This is a partially manual card. Do the extra attack by passing priority manually.");
      AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a card to attack and give +2");
      AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "MYALLY");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
      AddDecisionQueue("MZALLCARDTRAITORPASS", $currentPlayer, "Rebel", 1);
      AddDecisionQueue("MZOP", $currentPlayer, "GETUNIQUEID", 1);
      AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $currentPlayer, "3038238423,HAND", 1);
      break;
    case "3208391441"://Make an Opening
      Recover($currentPlayer, 2);
      $otherPlayer = $currentPlayer == 1 ? 2 : 1;
      AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a card to attack and give -2/-2");
      AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "THEIRALLY");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
      AddDecisionQueue("SETDQVAR", $currentPlayer, 0, 1);
      AddDecisionQueue("MZOP", $currentPlayer, "DEALDAMAGE,2", 1);
      AddDecisionQueue("PASSPARAMETER", $currentPlayer, "{0}", 1);
      AddDecisionQueue("MZOP", $otherPlayer, "GETUNIQUEID", 1);
      AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $otherPlayer, "3208391441,HAND", 1);
      break;
    case "2758597010"://Maximum Firepower
      for($i=0; $i<2; ++$i) {
        AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a card to deal damage");
        AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "MYALLY:trait=Imperial");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $currentPlayer, "POWER", 1);
        AddDecisionQueue("SETDQVAR", $currentPlayer, 0, 1);
        AddDecisionQueue("PASSPARAMETER", $currentPlayer, $target, 1);
        AddDecisionQueue("MZOP", $currentPlayer, "DEALDAMAGE,{0}", 1);
      }
      break;
    case "4263394087"://Chirrut Imwe
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Buff Defense") {
        AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a card to give +2 hp");
        AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "MYALLY");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $currentPlayer, "ADDHEALTH,2", 1);
        AddDecisionQueue("MZOP", $currentPlayer, "GETUNIQUEID", 1);
        AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $currentPlayer, "4263394087,HAND", 1);
      } else if($abilityName == "Deploy") {
        PlayAlly("d1a7b76ae7", $currentPlayer);
      }
      break;
    case "5154172446"://ISB Agent
      if($from != "PLAY") {
        AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a card to reveal");
        AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "MYHAND:definedType=Event");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $currentPlayer, "GETCARDID", 1);
        AddDecisionQueue("REVEALCARDS", $currentPlayer, "-", 1);
        AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a unit to deal 1 damage", 1);
        AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "THEIRALLY", 1);
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $currentPlayer, "DEALDAMAGE,1", 1);
      }
      break;
    case "4626028465"://Boba Fett
      PlayAlly("0e65f012f5", $currentPlayer);
      break;
    case "4300219753"://Fett's Firespray
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Exhaust") {
        AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a unit to deal exhaust");
        AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "THEIRALLY");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $currentPlayer, "REST", 1);
        $ally = new Ally("MYALLY-" . $index, $currentPlayer);
        $ally->Ready();
      }
      break;
    case "8009713136"://C-3PO
      AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a number");
      AddDecisionQueue("BUTTONINPUT", $currentPlayer, "0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20");
      AddDecisionQueue("SPECIFICCARD", $currentPlayer, "C3PO", 1);
      break;
    case "7911083239"://Grand Inquisitor
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Deal Damage") {
        AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a unit to deal 2 damage and ready");
        AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "MYALLY:maxPower=3");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $currentPlayer, "DEALDAMAGE,2", 1);
        AddDecisionQueue("MZOP", $currentPlayer, "READY", 1);
      } else if($abilityName == "Deploy") {
        PlayAlly("6827598372", $currentPlayer);
      }
      break;
    case "5954056864"://Han Solo
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Play Resource") {
        AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a card to resource");
        MZMoveCard($currentPlayer, "MYHAND", "MYRESOURCES", may:false);
        AddCurrentTurnEffect($cardID, $currentPlayer);
      } else if($abilityName == "Deploy") {
        PlayAlly("5e90bd91b0", $currentPlayer);
      }
      break;
    case "6514927936"://Leia Organa
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Attack") {
        WriteLog("This is a partially manual card. Do the extra attacks by passing priority manually.");
      } else if($abilityName == "Deploy") {
        PlayAlly("87e8807695", $currentPlayer);
      }
      break;
    case "8055390529"://Traitorous
      AddDecisionQueue("PASSPARAMETER", $currentPlayer, $target);
      AddDecisionQueue("MZOP", $currentPlayer, "TAKECONTROL");
      break;
    case "8244682354"://Jyn Erso
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Attack") {
        WriteLog("This is a partially manual card. Do the extra attacks by passing priority manually.");
        $otherPlayer = $currentPlayer == 1 ? 2 : 1;
        AddCurrentTurnEffect($cardID, $otherPlayer);
      } else if($abilityName == "Deploy") {
        PlayAlly("20f21b4948", $currentPlayer);
      }
      break;
    case "8327910265"://Energy Conversion Lab (ECL)
      global $CS_AfterPlayedBy;
      SetClassState($currentPlayer, $CS_AfterPlayedBy, $cardID);
      AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a card to put into play");
      AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "MYHAND:definedType=Unit;maxCost=6");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $currentPlayer, "PLAYCARD", 1);
      break;
    case "8600121285"://IG-88
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Attack") {
        WriteLog("This is a partially manual card. Do the extra attacks by passing priority manually.");
        if(HasMoreUnits($currentPlayer)) AddCurrentTurnEffect($cardID, $currentPlayer);
      } else if($abilityName == "Deploy") {
        PlayAlly("fb475d4ea4", $currentPlayer);
      }
      break;
    case "6954704048"://Heroic Sacrifice
      Draw($currentPlayer);
      WriteLog("This is a partially manual card. Do the extra attacks by passing priority manually.");
      AddCurrentTurnEffect($cardID, $currentPlayer);
      break;
    case "3426168686"://Sneak Attack
      global $CS_AfterPlayedBy;
      SetClassState($currentPlayer, $CS_AfterPlayedBy, $cardID);
      AddCurrentTurnEffect($cardID, $currentPlayer);
      AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a card to put into play");
      AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "MYHAND:definedType=Unit");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $currentPlayer, "PLAYCARD", 1);
      break;
    case "8800836530"://No Good To Me Dead
      $otherPlayer = $currentPlayer == 1 ? 2 : 1;
      AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a card to exhaust");
      AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "THEIRALLY");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $currentPlayer, "REST", 1);
      AddDecisionQueue("MZOP", $currentPlayer, "GETUNIQUEID", 1);
      AddDecisionQueue("ADDLIMITEDNEXTTURNEFFECT", $otherPlayer, "8800836530", 1);
      break;
    case "9097690846"://Snowtrooper Lieutenant
      if($from != "PLAY") {
        WriteLog("This is a partially manual card. Do the extra attack by passing priority manually.");
        AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a card to attack with");
        AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "MYALLY");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
        AddDecisionQueue("MZALLCARDTRAITORPASS", $currentPlayer, "Imperial", 1);
        AddDecisionQueue("MZOP", $currentPlayer, "GETUNIQUEID", 1);
        AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $currentPlayer, "9097690846", 1);
      }
      break;
    case "9210902604"://Precision Fire
      WriteLog("This is a partially manual card. Do the extra attack by passing priority manually.");
      AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a card to attack with");
      AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "MYALLY");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $currentPlayer, "GETUNIQUEID", 1);
      AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $currentPlayer, "9210902604", 1);
      break;
    case "7870435409"://Bib Fortuna
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Play Event") {
        AddCurrentTurnEffect($cardID, $currentPlayer);
        AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose an event to play");
        AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "MYHAND:definedType=Event");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $currentPlayer, "PLAYCARD", 1);
      }
      break;
    case "8297630396"://Shoot First
      WriteLog("This is a partially manual card. Do the extra attack by passing priority manually.");
      AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a card to attack with");
      AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "MYALLY");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $currentPlayer, "GETUNIQUEID", 1);
      AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $currentPlayer, "8297630396", 1);
      break;
    case "5767546527"://For a Cause I Believe In
      $deck = new Deck($currentPlayer);
      $deck->Reveal(4);
      $cards = $deck->Top(remove:true, amount:4);
      $cardArr = explode(",", $cards);
      $damage = 0;
      for($i=0; $i<count($cardArr); ++$i) {
        if(AspectContains($cardArr[$i], "Heroism", $currentPlayer)) {
          ++$damage;
        }
      }
      WriteLog(CardLink($cardID, $cardID) . " is dealing " . $damage . " damage. Pass to discard the rest of the cards.");
      DealArcane($damage, 1, "PLAYCARD", "5767546527");
      if($cards != "") {
        global $dqVars;
        $dqVars[0] = $cards;
        AddDecisionQueue("MAYCHOOSETOP", $currentPlayer, $cards);
        AddDecisionQueue("SPECIFICCARD", $currentPlayer, "FORACAUSEIBELIEVEIN");
      }
      break;
    case "5784497124"://Emperor Palpatine
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Deal Damage") {
        AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose an ally to destroy");
        AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "MYALLY");
        AddDecisionQueue("CHOOSEMULTIZONE", $currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $currentPlayer, "DESTROY", 1);
        AddDecisionQueue("DRAW", $currentPlayer, "-", 1);
        AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose an ally to deal 1 damage");
        AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "THEIRALLY");
        AddDecisionQueue("CHOOSEMULTIZONE", $currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $currentPlayer, "DEALDAMAGE,1", 1);
      } else if($abilityName == "Deploy") {
        AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "THEIRALLY");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $currentPlayer, "TAKECONTROL", 1);
        PlayAlly("6c5b96c7ef", $currentPlayer);
      }
      break;
    case "8117080217"://Admiral Ozzel
      $abilityName = GetResolvedAbilityName($cardID, $from);
      if($abilityName == "Play Imperial Unit") {
        global $CS_AfterPlayedBy;
        SetClassState($currentPlayer, $CS_AfterPlayedBy, $cardID);
        AddDecisionQueue("SETDQCONTEXT", $currentPlayer, "Choose a card to put into play");
        AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "MYHAND:definedType=Unit;trait=Imperial");
        AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
        AddDecisionQueue("MZOP", $currentPlayer, "PLAYCARD", 1);
      }
      break;
    case "1626462639"://Change of Heart
      AddDecisionQueue("MULTIZONEINDICES", $currentPlayer, "THEIRALLY");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $currentPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $currentPlayer, "TAKECONTROL", 1);
      break;
    case "2855740390"://Lieutenant Childsen
      if($from != "PLAY") {
        $hand = &GetHand($currentPlayer);
        $ally = new Ally("MYALLY-" . LastAllyIndex($currentPlayer), $currentPlayer);
        $toReveal = "";
        $amount = 0;
        for($i=0; $i<count($hand); $i+=HandPieces()) {
          if($amount < 4 && AspectContains($hand[$i], "Vigilance", $currentPlayer)) {
            $ally->Attach("2007868442");//Experience token
            if($toReveal != "") $toReveal .= ",";
            $toReveal .= $hand[$i];
            ++$amount;
          }
        }
        RevealCards($toReveal, $currentPlayer, "HAND");
      }
      break;
    case "8506660490"://Darth Vader
      if($from != "PLAY") {
        $hand = &GetHand($currentPlayer);
        AddDecisionQueue("FINDINDICES", $currentPlayer, "DECKTOPXREMOVE," . 10);
        AddDecisionQueue("SETDQVAR", $currentPlayer, "0", 1);
        AddDecisionQueue("FILTER", $currentPlayer, "LastResult-include-trait-Imperial", 1);
        AddDecisionQueue("MAYCHOOSECARD", $currentPlayer, "<-", 1);
        AddDecisionQueue("ADDHAND", $currentPlayer, "-", 1);
        AddDecisionQueue("OP", $currentPlayer, "REMOVECARD", 1);
        AddDecisionQueue("PASSPARAMETER", $currentPlayer, "MYHAND-" . count($hand), 1);
        AddDecisionQueue("MZOP", $currentPlayer, "PLAYCARD", 1);
      }
      break;
    default: break;
  }
}

function ReadyResource($player, $amount=1) {
  $resourceCards = &GetResourceCards($player);
  $numReadied = 0;
  for($i=0; $i<count($resourceCards) && $numReadied < $amount; $i+=ResourcePieces()) {
    if($resourceCards[$i + 4] == 1) {
      ++$numReadied;
      $resourceCards[$i + 4] = 0;
    }
  }
}

function ExhaustResource($player, $amount=1) {
  $resourceCards = &GetResourceCards($player);
  $numExhausted = 0;
  for($i=0; $i<count($resourceCards) && $numExhausted < $amount; $i+=ResourcePieces()) {
    if($resourceCards[$i + 4] == 0) {
      ++$numExhausted;
      $resourceCards[$i + 4] = 1;
    }
  }
}

function AfterPlayedByAbility($cardID) {
  global $currentPlayer;
  $index = LastAllyIndex($currentPlayer);
  $ally = new Ally("MYALLY-" . $index, $currentPlayer);
  switch($cardID) {
    case "3572356139"://Chewbacca, Walking Carpet
      AddDecisionQueue("OP", $currentPlayer, "GETLASTALLYMZ");
      AddDecisionQueue("MZOP", $currentPlayer, "GETUNIQUEID", 1);
      AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $currentPlayer, "3572356139,PLAY", 1);
      break;
    case "5494760041"://Galactic Ambition
      AddDecisionQueue("PASSPARAMETER", $currentPlayer, "{1}", 1);
      AddDecisionQueue("SPECIFICCARD", $currentPlayer, "GALACTICAMBITION", 1);
      break;
    case "8327910265"://Energy Conversion Lab (ECL)
      AddCurrentTurnEffect($cardID, $currentPlayer, "PLAY", $ally->UniqueID());
      break;
    case "3426168686"://Sneak Attack
      AddDecisionQueue("OP", $currentPlayer, "GETLASTALLYMZ");
      AddDecisionQueue("MZOP", $currentPlayer, "READY", 1);
      AddDecisionQueue("MZOP", $currentPlayer, "GETUNIQUEID", 1);
      AddDecisionQueue("ADDLIMITEDCURRENTEFFECT", $currentPlayer, "3426168686,PLAY", 1);
      break;
    case "8117080217"://Admiral Ozzel
      $ally->Ready();
      $otherPlayer = $currentPlayer == 1 ? 2 : 1;
      AddDecisionQueue("SETDQCONTEXT", $otherPlayer, "Choose a unit to ready");
      AddDecisionQueue("MULTIZONEINDICES", $otherPlayer, "MYALLY");
      AddDecisionQueue("MAYCHOOSEMULTIZONE", $otherPlayer, "<-", 1);
      AddDecisionQueue("MZOP", $otherPlayer, "READY", 1);
      break;
    default: break;
  }
  if(HasAmbush($cardID, $currentPlayer, $index)) {
    $ally->Ready();
    WriteLog("This is a partially manual effect. Pass priority manually to resolve the ambush attack.");
  }
}

function MemoryCount($player) {
  $memory = &GetMemory($player);
  return count($memory)/MemoryPieces();
}

function MemoryRevealRandom($player, $returnIndex=false)
{
  $memory = &GetMemory($player);
  $rand = GetRandom()%(count($memory)/MemoryPieces());
  $index = $rand*MemoryPieces();
  $toReveal = $memory[$index];
  $wasRevealed = RevealCards($toReveal);
  return $wasRevealed ? ($returnIndex ? $toReveal : $index) : ($returnIndex ? -1 : "");
}

function ExhaustAllAllies($arena, $player)
{
  $allies = &GetAllies($player);
  for($i=0; $i<count($allies); $i+=AllyPieces())
  {
    if(CardArenas($allies[$i]) == $arena) {
      $ally = new Ally("MYALLY-" . $i, $player);
      $ally->Exhaust();
    }
  }
}

function DestroyAllAllies()
{
  global $currentPlayer;
  $otherPlayer = $currentPlayer == 1 ? 2 : 1;
  $theirAllies = &GetAllies($otherPlayer);
  for($i=count($theirAllies) - AllyPieces(); $i>=0; $i-=AllyPieces())
  {
    DestroyAlly($otherPlayer, $i);
  }
  $allies = &GetAllies($currentPlayer);
  for($i=count($allies) - AllyPieces(); $i>=0; $i-=AllyPieces())
  {
    DestroyAlly($currentPlayer, $i);
  }
}

function DamagePlayerAllies($player, $damage, $source, $type, $arena="")
{
  $allies = &GetAllies($player);
  for($i=count($allies)-AllyPieces(); $i>=0; $i-=AllyPieces())
  {
    if($arena != "" && !ArenaContains($allies[$i], $arena, $player)) continue;
    $ally = new Ally("MYALLY-" . $i, $player);
    $ally->DealDamage($damage);
  }
}

function DamageAllAllies($amount, $source, $alsoRest=false, $alsoFreeze=false, $arena="")
{
  global $currentPlayer;
  $otherPlayer = $currentPlayer == 1 ? 2 : 1;
  $theirAllies = &GetAllies($otherPlayer);
  for($i=count($theirAllies) - AllyPieces(); $i>=0; $i-=AllyPieces())
  {
    if(!ArenaContains($theirAllies[$i], $arena, $otherPlayer)) continue;
    if($alsoRest) $theirAllies[$i+1] = 1;
    if($alsoFreeze) $theirAllies[$i+3] = 1;
    DealArcane($amount, source:$source, resolvedTarget:"THEIRALLY-$i");
  }
  $allies = &GetAllies($currentPlayer);
  for($i=count($allies) - AllyPieces(); $i>=0; $i-=AllyPieces())
  {
    if(!ArenaContains($allies[$i], $arena, $currentPlayer)) continue;
    if($alsoRest) $allies[$i+1] = 1;
    if($alsoFreeze) $allies[$i+3] = 1;
    DealArcane($amount, source:$source, resolvedTarget:"MYALLY-$i");
  }
}



function IsHarmonizeActive($player)
{
  global $CS_NumMelodyPlayed;
  return GetClassState($player, $CS_NumMelodyPlayed) > 0;
}

function AddPreparationCounters($player, $amount=1)
{
  global $CS_PreparationCounters;
  IncrementClassState($player, $CS_PreparationCounters, $amount);
}

function DrawIntoMemory($player)
{
  $deck = &GetDeck($player);
  if(count($deck) > 0) AddMemory(array_shift($deck), $player, "DECK", "DOWN");
}

function Mill($player, $amount)
{
  $cards = "";
  $deck = &GetDeck($player);
  if($amount > count($deck)) $amount = count($deck);
  for($i=0; $i<$amount; ++$i)
  {
    $card .= array_shift($deck);
    if($cards != "") $cards .= ",";
    $cards .= $card;
    AddGraveyard($card, $player, "DECK");
  }
  return $cards;
}

function Recover($player, $amount)
{
  $health = &GetHealth($player);
  if($amount > $health) $health = 0;
  else $health -= $amount;
}


//target type return values
//-1: no target
// 0: My Hero + Their Hero
// 1: Their Hero only
// 2: Any Target
// 3: Their Hero + Their Allies
// 4: My Hero only (For afflictions)
function PlayRequiresTarget($cardID)
{
  global $currentPlayer;
  if(DefinedTypesContains($cardID, "Upgrade", $currentPlayer)) return 2;
  switch($cardID)
  {
    case "8679831560": return 2;//Repair
    //Only allies v
    case "8148673131": return 2;//Open Fire
    case "8981523525": return 2;//Moment of Peace
    case "7202133736": return 2;//Waylay
    case "0827076106": return 2;//Admiral Ackbar
    case "0867878280": return 2;//It Binds All Things
    case "2587711125": return 2;//Disarm
    case "2569134232": return 2;//Jedha City
    case "6515891401": return 2;//Karabast
    case "1349057156": return 2;//Strike True
    case "2651321164": return 2;//Tactical Advantage
    case "1900571801": return 2;//Overwhelming Barrage
    case "7861932582": return 2;//The Force is With Me
    case "2758597010": return 2;//Maximum Firepower
    default: return -1;
  }
}

  function ArcaneDamage($cardID)
  {
    global $currentPlayer;
    switch($cardID)
    {
      case "8148673131": return 4;//Open Fire
      return 0;
    }
  }

  //Parameters:
  //Player = Player controlling the arcane effects
  //target = See function PlayRequiresTarget
  function DealArcane($damage, $target=0, $type="PLAYCARD", $source="NA", $fromQueue=false, $player=0, $mayAbility=false, $limitDuplicates=false, $skipHitEffect=false, $resolvedTarget="", $nbArcaneInstance=1)
  {
    global $currentPlayer, $CS_ArcaneTargetsSelected;
    if ($player == 0) $player = $currentPlayer;
    if ($damage > 0) {
      //$damage += CurrentEffectArcaneModifier($source, $player) * $nbArcaneInstance;
      if ($type != "PLAYCARD") WriteLog(CardLink($source, $source) . " is dealing " . $damage . " arcane damage.");
      if ($fromQueue) {
        if (!$limitDuplicates) {
          PrependDecisionQueue("PASSPARAMETER", $player, "{0}");
          PrependDecisionQueue("SETCLASSSTATE", $player, $CS_ArcaneTargetsSelected); //If already selected for arcane multiselect (e.g. Singe/Azvolai)
          PrependDecisionQueue("PASSPARAMETER", $player, "-");
        }
        if (!$skipHitEffect) PrependDecisionQueue("ARCANEHITEFFECT", $player, $source, 1);
        PrependDecisionQueue("DEALARCANE", $player, $damage . "-" . $source . "-" . $type, 1);
        if ($resolvedTarget != "") {
          PrependDecisionQueue("PASSPARAMETER", $currentPlayer, $resolvedTarget);
        } else {
          PrependDecisionQueue("SETDQVAR", $currentPlayer, "0", 1);
          if ($mayAbility) {
            PrependDecisionQueue("MAYCHOOSEMULTIZONE", $player, "<-", 1);
          } else {
            PrependDecisionQueue("CHOOSEMULTIZONE", $player, "<-", 1);
          }
          PrependDecisionQueue("SETDQCONTEXT", $player, "Choose a target for <0>");
          PrependDecisionQueue("FINDINDICES", $player, "ARCANETARGET," . $target);
          PrependDecisionQueue("SETDQVAR", $currentPlayer, "0");
          PrependDecisionQueue("PASSPARAMETER", $currentPlayer, $source);
        }
      } else {
        if ($resolvedTarget != "") {
          AddDecisionQueue("PASSPARAMETER", $currentPlayer, $resolvedTarget);
        } else {
          AddDecisionQueue("PASSPARAMETER", $currentPlayer, $source);
          AddDecisionQueue("SETDQVAR", $currentPlayer, "0");
          AddDecisionQueue("FINDINDICES", $player, "ARCANETARGET," . $target);
          AddDecisionQueue("SETDQCONTEXT", $player, "Choose a target for <0>");
          if ($mayAbility) {
            AddDecisionQueue("MAYCHOOSEMULTIZONE", $player, "<-", 1);
          } else {
            AddDecisionQueue("CHOOSEMULTIZONE", $player, "<-", 1);
          }
          AddDecisionQueue("SETDQVAR", $currentPlayer, "0", 1);
        }
        AddDecisionQueue("DEALARCANE", $player, $damage . "-" . $source . "-" . $type, 1);
        if (!$skipHitEffect) AddDecisionQueue("ARCANEHITEFFECT", $player, $source, 1);
        if (!$limitDuplicates) {
          AddDecisionQueue("PASSPARAMETER", $player, "-");
          AddDecisionQueue("SETCLASSSTATE", $player, $CS_ArcaneTargetsSelected);
          AddDecisionQueue("PASSPARAMETER", $player, "{0}");
        }
      }
    }
  }

  function ArcaneHitEffect($player, $source, $target, $damage)
  {

  }

  //target type return values
  //-1: no target
  // 0: My Hero + Their Hero
  // 1: Their Hero only
  // 2: Any Target
  // 3: Their Allies
  // 4: My Hero only (For afflictions)
  function GetArcaneTargetIndices($player, $target)
  {
    global $CS_ArcaneTargetsSelected;
    $otherPlayer = ($player == 1 ? 2 : 1);
    if ($target == 4) return "MYCHAR-0";
    if($target != 3) $rv = "THEIRCHAR-0";
    else $rv = "";
    if(($target == 0 && !ShouldAutotargetOpponent($player)) || $target == 2)
    {
      $rv .= ",MYCHAR-0";
    }
    if($target == 2)
    {
      $theirAllies = &GetAllies($otherPlayer);
      for($i=0; $i<count($theirAllies); $i+=AllyPieces())
      {
        $rv .= ",THEIRALLY-" . $i;
      }
      $myAllies = &GetAllies($player);
      for($i=0; $i<count($myAllies); $i+=AllyPieces())
      {
        $rv .= ",MYALLY-" . $i;
      }
    }
    elseif($target == 3 || $target == 5)
    {
      $theirAllies = &GetAllies($otherPlayer);
      for($i=0; $i<count($theirAllies); $i+=AllyPieces())
      {
        if($rv != "") $rv .= ",";
        $rv .= "THEIRALLY-" . $i;
      }
    }
    $targets = explode(",", $rv);
    $targetsSelected = GetClassState($player, $CS_ArcaneTargetsSelected);
    for($i=count($targets)-1; $i>=0; --$i)
    {
      if(DelimStringContains($targetsSelected, $targets[$i])) unset($targets[$i]);
    }
    return implode(",", $targets);
  }

function CountPitch(&$pitch, $min = 0, $max = 9999)
{
  $pitchCount = 0;
  for($i = 0; $i < count($pitch); ++$i) {
    $cost = CardCost($pitch[$i]);
    if($cost >= $min && $cost <= $max) ++$pitchCount;
  }
  return $pitchCount;
}

function HandIntoMemory($player)
{
  AddDecisionQueue("MULTIZONEINDICES", $player, "MYHAND");
  AddDecisionQueue("SETDQCONTEXT", $player, "Choose a card to put into memory", 1);
  AddDecisionQueue("CHOOSEMULTIZONE", $player, "<-", 1);
  AddDecisionQueue("MZADDZONE", $player, "MYMEMORY,HAND,DOWN", 1);
  AddDecisionQueue("MZREMOVE", $player, "-", 1);
}

function Draw($player, $mainPhase = true, $fromCardEffect = true)
{
  global $EffectContext, $mainPlayer;
  $otherPlayer = ($player == 1 ? 2 : 1);
  $deck = &GetDeck($player);
  $hand = &GetHand($player);
  if(count($deck) == 0) return -1;
  if(CurrentEffectPreventsDraw($player, $mainPhase)) return -1;
  array_push($hand, array_shift($deck));
  PermanentDrawCardAbilities($player);
  $hand = array_values($hand);
  return $hand[count($hand) - 1];
}

function WakeUpChampion($player)
{
  $char = &GetPlayerCharacter($player);
  $char[1] = 2;
}

