<?php

//Known hooks across networks (we do not distinct networks)
return [

  //XAHAU specific
  '610F33B8EBF7EC795F822A454FB852156AEFE50BE0CB8326338A81CD74801864' => [
    'title' => 'Xahau Governance Reward Hook',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/610F33B8EBF7EC795F822A454FB852156AEFE50BE0CB8326338A81CD74801864.webp',
    'web' => 'https://docs.xahau.network/technical/balance-adjustments',
    'descr' => "This hook allows Balance Adjustments on Xahau Network",
    'source' => 'https://github.com/Xahau/xahaud/blob/dev/hook/genesis/reward.c',
  ],
  '5EDF6439C47C423EAC99C1061EE2A0CE6A24A58C8E8A66E4B3AF91D76772DC77' => [
    'title' => 'Xahau Governance Game Hook',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/5EDF6439C47C423EAC99C1061EE2A0CE6A24A58C8E8A66E4B3AF91D76772DC77.webp',
    'web' => 'https://docs.xahau.network/technical/governance-game',
    'descr' => "The Governance Game is an innovative governance mechanism within the Xahau ecosystem to ensure a community-centric approach towards decision-making.",
    'source' => 'https://github.com/Xahau/xahaud/blob/dev/hook/genesis/govern.c',
  ],

  'FAC0FAF928B48D7D113B95A07129E5E161AA5CCFCB4AEE0BC49B5795645CEFCD' => [
    'title' => 'Xahau Governance Game Hook v2',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/5EDF6439C47C423EAC99C1061EE2A0CE6A24A58C8E8A66E4B3AF91D76772DC77.webp',
    'web' => 'https://docs.xahau.network/technical/governance-game',
    'descr' => "The Governance Game is an innovative governance mechanism within the Xahau ecosystem to ensure a community-centric approach towards decision-making.\n\nFixed Governance Game Hook, vote purge bug is fixed with this version.\n\nSee issue: https://github.com/Xahau/xahaud/issues/211",
    'source' => 'https://github.com/Xahau/xahaud/blob/dev/hook/genesis/govern.c',
  ],


  //Evernode specific
  '998EECBC04313E634A6AE7D0C10F28027BE502073D241AC867BC1ECB4DC03006' => [
    'title' => 'Evernode Airdrop Distributor',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rhMboq72S1sLBBxv4PS6ezwJLbgfSJFG4b.webp',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],
  '5BAF3AD0CE10466E753348E04E65EE17DC797E898105F1913BF90EE46F349BB1' => [
    'title' => 'Evernode Heartbeat Hook v1',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rHktfGUbjqzU4GsYCMc1pDjdHXb5CJamto.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/tree/main/evernode-heartbeat-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  'E2F03697C83603E6E1185F77688216AF537383B7CBB367122AA0EBB4EE2B803E' => [
    'title' => 'Evernode Heartbeat Hook v2',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rHktfGUbjqzU4GsYCMc1pDjdHXb5CJamto.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/tree/main/evernode-heartbeat-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  'C6ECD5DDE11B1D03DA1DA66D48556B954AB4BAF307EB8182DB5579BB9F712DB3' => [
    'title' => 'Evernode Heartbeat Hook v3',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rHktfGUbjqzU4GsYCMc1pDjdHXb5CJamto.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/tree/main/evernode-heartbeat-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  '00292AC721D036805124593754A924179439C1806370B8C1E8E8E20A6EA42EB8' => [
    'title' => 'Evernode Heartbeat Hook v4',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rHktfGUbjqzU4GsYCMc1pDjdHXb5CJamto.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/tree/main/evernode-heartbeat-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  '4D2F7137D98ABE42D82400F2B6DCED604C108B8343E499A17F0F5C431D55A67C' => [
    'title' => 'Evernode Heartbeat Hook v5',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rHktfGUbjqzU4GsYCMc1pDjdHXb5CJamto.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/tree/main/evernode-heartbeat-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  '39924D144FF831C10445D296101C5F9C2B2E9E452A4DABCF99AED17B6847BA83' => [
    'title' => 'Evernode Heartbeat Hook v6',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rHktfGUbjqzU4GsYCMc1pDjdHXb5CJamto.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/tree/main/evernode-heartbeat-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  '63F740F2DCA41B8D0F5AA7C4CB5A6DEF6157AB5381FE4B2CD618D710CEDBB993' => [
    'title' => 'Evernode Heartbeat Hook v7',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rHktfGUbjqzU4GsYCMc1pDjdHXb5CJamto.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/tree/main/evernode-heartbeat-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  '5A485E18573715539A17E52602E75DE5F228A3207AFC21BC798F7213462D27AB' => [
    'title' => 'Evernode Heartbeat Hook v8',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rHktfGUbjqzU4GsYCMc1pDjdHXb5CJamto.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/tree/main/evernode-heartbeat-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  '2363D363E262F28C045F1435B31B274FAB3D563513257516840F8A5E840186F8' => [
    'title' => 'Evernode Heartbeat Hook v9',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rHktfGUbjqzU4GsYCMc1pDjdHXb5CJamto.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/tree/main/evernode-heartbeat-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  '117D45B4620946B47F4AEEC36853480E685712CA7105A2C9203C0A679B75B911' => [
    'title' => 'Evernode Heartbeat Hook v10',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rHktfGUbjqzU4GsYCMc1pDjdHXb5CJamto.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/tree/main/evernode-heartbeat-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  '4F75DBFBE14A30B92EE677E8BC4A7D17504F56DAA1800F468B275D65149E1EE0' => [
    'title' => 'Evernode Heartbeat Hook v11',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rHktfGUbjqzU4GsYCMc1pDjdHXb5CJamto.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/tree/main/evernode-heartbeat-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  '465117F5604C648F9FCF075110C1B43A21DC67ACBA838FD40C9E02C86709F6B8' => [
    'title' => 'Evernode Heartbeat Hook v12',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rHktfGUbjqzU4GsYCMc1pDjdHXb5CJamto.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/tree/main/evernode-heartbeat-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  '4F206E93C1EA0AB857DF818F5E60A035F6661142E9D0D82EB5B9D1C524DEE512' => [
    'title' => 'Evernode Heartbeat Hook v13',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rHktfGUbjqzU4GsYCMc1pDjdHXb5CJamto.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/tree/main/evernode-heartbeat-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  'C52E991367F007E8591FEE0A03ACB7249C60A08B97FCE52E3A9F9E84ADA7D9F9' => [
    'title' => 'Evernode Heartbeat Hook v14',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rHktfGUbjqzU4GsYCMc1pDjdHXb5CJamto.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/tree/main/evernode-heartbeat-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  'D0F101EC1EC870512CA9B8E67D8DDAF00E3F4AB036DAFA7F5E1C4211B44BB965' => [
    'title' => 'Evernode Heartbeat Hook v14.1',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rHktfGUbjqzU4GsYCMc1pDjdHXb5CJamto.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/tree/proposal-v2/evernode-heartbeat-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
    'descr' => "This hook is the same as v14, it has small trivial change to create new hash so it can be voted-in in a Governance Game for demonstration purposes.",
  ],

  '1F7C84E14313C4FF2D4F39535428BF10767CCF8E87EFB51306CC3F94D13439EC' => [ //latest
    'title' => 'Evernode Heartbeat Hook v15',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rHktfGUbjqzU4GsYCMc1pDjdHXb5CJamto.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/tree/main/evernode-heartbeat-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  'F3D61A99804C8A825611427E3BC9070CEA2F0E26EFB5702D984202EB10A0AFF8' => [
    'title' => 'Evernode Reputation Hook v1',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rsfTBRAbD2bYjVuXhJ2RReQXxR4K5birVW.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/tree/main/evernode-reputation-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  '783355BBA926AC3456CD2C9F5CADCFC7940C00A31332A8AF8B695E801D21D829' => [
    'title' => 'Evernode Reputation Hook v2',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rsfTBRAbD2bYjVuXhJ2RReQXxR4K5birVW.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/tree/main/evernode-reputation-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  '399D3283343A38009ED8A3169ED63F1A06AD85C796F0A5EA5F32CBC782783BDA' => [
    'title' => 'Evernode Reputation Hook v3',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rsfTBRAbD2bYjVuXhJ2RReQXxR4K5birVW.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/tree/main/evernode-reputation-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  '53A2B9487D0578FDDDA1EC48A54A9BF79D62AA38EFC6E606EA03E0D4067B8B82' => [
    'title' => 'Evernode Reputation Hook v4',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rsfTBRAbD2bYjVuXhJ2RReQXxR4K5birVW.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/tree/main/evernode-reputation-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  '245E717B644631E53B6A88C957C07A2580AA51EB7CE72E0BB9F0CA3B117A285E' => [
    'title' => 'Evernode Reputation Hook v5',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rsfTBRAbD2bYjVuXhJ2RReQXxR4K5birVW.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/tree/main/evernode-reputation-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  'D476D917251732BE14823AB47A147056B68CF6BCE97D04323789B93C83FDE90A' => [
    'title' => 'Evernode Reputation Hook v6',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rsfTBRAbD2bYjVuXhJ2RReQXxR4K5birVW.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/tree/main/evernode-reputation-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  'E379A9195A9B788718038F98CB84DD358CC0DAD8760C2A0BCE72A8912B033A47' => [
    'title' => 'Evernode Reputation Hook v7',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rsfTBRAbD2bYjVuXhJ2RReQXxR4K5birVW.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/tree/main/evernode-reputation-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  '17AFCBEE5F1709A95BE9E18C0893F49D7AAF8CD14726E8BE8C83B74A65EF28BF' => [
    'title' => 'Evernode Reputation Hook v8',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rsfTBRAbD2bYjVuXhJ2RReQXxR4K5birVW.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/tree/main/evernode-reputation-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  '3BD83376EC6E57BC83E81720730F8C79880E52ACA5351FA9FDE4F38247B1E3D2' => [
    'title' => 'Evernode Reputation Hook v9',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rsfTBRAbD2bYjVuXhJ2RReQXxR4K5birVW.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/tree/main/evernode-reputation-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  '838C6941AB5B5460AEC46A535B98A9A55A6CEBF9F5CE74F48BA58E065FBB7899' => [
    'title' => 'Evernode Reputation Hook v9.1',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rsfTBRAbD2bYjVuXhJ2RReQXxR4K5birVW.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/tree/proposal-v2/evernode-reputation-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
    'descr' => "This hook is the same as v9, it has small trivial change to create new hash so it can be voted-in in a Governance Game for demonstration purposes.",
  ],

  'EFBB4898CD57CD274636431DBC7E90F04C49EA4721A754F7C40E471C381D91A4' => [ //latest
    'title' => 'Evernode Reputation Hook v10',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rsfTBRAbD2bYjVuXhJ2RReQXxR4K5birVW.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/tree/main/evernode-reputation-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  '6CDDD0CF3275DBBD5A3EE83049829E578BA23DB97D0D1C0C7759ED2A812FED22' => [
    'title' => 'Evernode Registry Hook v1',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rmv53yu8Wid6kj6AC6NvmiwSXNxRa8vTH.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/blob/main/evernode-registry-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  'BFF584887A6EE876B49548CB79CA2C5A282ACFEF4B9B5B8FE4EE96A10D73DAAC' => [
    'title' => 'Evernode Registry Hook v2',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rmv53yu8Wid6kj6AC6NvmiwSXNxRa8vTH.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/blob/main/evernode-registry-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  'FB7159503D2F6572B539B65994028B1E945871A5BCC7BB2ECDF63CE34ECB2945' => [
    'title' => 'Evernode Registry Hook v3',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rmv53yu8Wid6kj6AC6NvmiwSXNxRa8vTH.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/blob/main/evernode-registry-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  'B4CD57361FB5C8E60ADE69E3D8D6217A9EFFE7C11B19B2681D650522872D63BE' => [
    'title' => 'Evernode Registry Hook v4',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rmv53yu8Wid6kj6AC6NvmiwSXNxRa8vTH.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/blob/main/evernode-registry-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  '4A44C6DB507CADA2DCF8518543957020FA2D3BA74BB4B23F3EBAFECA462AB04C' => [
    'title' => 'Evernode Registry Hook v5',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rmv53yu8Wid6kj6AC6NvmiwSXNxRa8vTH.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/blob/main/evernode-registry-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  'A524275CB35E9FF13FF048FF7BE1A23DA4F8918DF46F9C2DC1D5ECC63D4C4AFC' => [
    'title' => 'Evernode Registry Hook v6',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rmv53yu8Wid6kj6AC6NvmiwSXNxRa8vTH.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/blob/main/evernode-registry-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  '7E0DC82309C7A4940F7F51BC170F29C0A88666AAC478C29EE295DFB68D40D6F7' => [
    'title' => 'Evernode Registry Hook v7',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rmv53yu8Wid6kj6AC6NvmiwSXNxRa8vTH.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/blob/main/evernode-registry-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  'EE5874EC57FBF6DC164DFA3498656A8D0B49B9614132FBE32BF93312ED60FA48' => [
    'title' => 'Evernode Registry Hook v8',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rmv53yu8Wid6kj6AC6NvmiwSXNxRa8vTH.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/blob/main/evernode-registry-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  '27C590B1C6CBA2A550820B6AC59F2A041226CD4110ED0CEF73C3139D1102AA14' => [
    'title' => 'Evernode Registry Hook v9',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rmv53yu8Wid6kj6AC6NvmiwSXNxRa8vTH.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/blob/main/evernode-registry-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  'C6B3A709812DF63AD97EED260B2E76C54FFCC5EB88448B22670E66EBC973F48C' => [
    'title' => 'Evernode Registry Hook v10',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rmv53yu8Wid6kj6AC6NvmiwSXNxRa8vTH.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/blob/main/evernode-registry-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  '2D5BDDA4A3230EA1664144C7411AB810F1BEA56A1116AC2F01E151864A7D5E2F' => [
    'title' => 'Evernode Registry Hook v10.1',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rmv53yu8Wid6kj6AC6NvmiwSXNxRa8vTH.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/blob/proposal-v2/evernode-registry-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
    'descr' => "This hook is the same as v10, it has small trivial change to create new hash so it can be voted-in in a Governance Game for demonstration purposes.",
  ],

  'B352CB9916C8CA2A47A500EBBD93EBADDC933FB82347B2B95E87B70186D06127' => [ //latest
    'title' => 'Evernode Registry Hook v11',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rmv53yu8Wid6kj6AC6NvmiwSXNxRa8vTH.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/blob/main/evernode-registry-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  'C820233A5F9D204907EAF43E6CD57D6D60DAA7DAFE3D2F8741CFAE9D873DA910' => [
    'title' => 'Evernode Governor Hook v1',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rBvKgF3jSZWdJcwSsmoJspoXLLDVLDp6jg.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/tree/main/evernode-governor-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  'ECA20A1C94A6A8C97E4A114BC8C755BBDC887B0161C8E30246955CFAA34D6A0B' => [
    'title' => 'Evernode Governor Hook v2',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rBvKgF3jSZWdJcwSsmoJspoXLLDVLDp6jg.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/tree/main/evernode-governor-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  '4A17BB25F88CFCEB03565BAF99B9D687683C4C523F7F1D76251EDAE0F0F2D169' => [
    'title' => 'Evernode Governor Hook v3',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rBvKgF3jSZWdJcwSsmoJspoXLLDVLDp6jg.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/tree/main/evernode-governor-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  'BEEC851876FD1EC90885FDEF02ACDEE3BEE2C1BFA5D1945FF46955CA4353BDE5' => [
    'title' => 'Evernode Governor Hook v4',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rBvKgF3jSZWdJcwSsmoJspoXLLDVLDp6jg.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/tree/main/evernode-governor-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  '926AEAC9C498C8E63FC34CFA2CE165061F4FA02FD181D6A64F9707586C0FEE6B' => [
    'title' => 'Evernode Governor Hook v5',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rBvKgF3jSZWdJcwSsmoJspoXLLDVLDp6jg.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/tree/main/evernode-governor-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  '21546DBFE50FA84EDB09E211715F2E4F08CA2C88479BA5F4BE0E9ADBF69596A7' => [
    'title' => 'Evernode Governor Hook v6',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rBvKgF3jSZWdJcwSsmoJspoXLLDVLDp6jg.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/tree/main/evernode-governor-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  'BE9B008C36FB4240B2A5AC13183432CD449F6C898754E080559BA33F146CB5B7' => [
    'title' => 'Evernode Governor Hook v7',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rBvKgF3jSZWdJcwSsmoJspoXLLDVLDp6jg.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/tree/main/evernode-governor-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  'EB9A3C1BFB4DC14AE47A43E527FD1B1E862E58C16AED8CDD1C7F08A3E0E20698' => [
    'title' => 'Evernode Governor Hook v8',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rBvKgF3jSZWdJcwSsmoJspoXLLDVLDp6jg.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/tree/main/evernode-governor-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  'AC81F69627F684AF861205341AB8C0B2CAD41ED0C7FBB59F1C527F9095241B58' => [
    'title' => 'Evernode Governor Hook v9',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rBvKgF3jSZWdJcwSsmoJspoXLLDVLDp6jg.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/tree/main/evernode-governor-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
    'descr' => "This hook changed candidate support average from 80% to 66% due to low response from Evernode Hosts, in this time period Evernode Governance voting was in testing phase to vote in new hooks, next hook is this one: DD68B11C3176CA0DD3ED815F0147807C9865C04A42AB257EC089715E49B0EF4A.",
  ],

  'DD68B11C3176CA0DD3ED815F0147807C9865C04A42AB257EC089715E49B0EF4A' => [
    'title' => 'Evernode Governor Hook v9.1',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rBvKgF3jSZWdJcwSsmoJspoXLLDVLDp6jg.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/tree/proposal-v2/evernode-governor-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
    'descr' => "This hook is the same as v8, it has small trivial change to create new hash so it can be voted-in in a Governance Game for demonstration purposes.",
  ],

  'B0B11C2179638C3EFF12D52454AD46DBB22AB89DBBDB0497028AA7FA88677678' => [ //latest
    'title' => 'Evernode Governor Hook v10',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rBvKgF3jSZWdJcwSsmoJspoXLLDVLDp6jg.webp',
    'source' => 'https://github.com/EvernodeXRPL/evernode-hook/tree/main/evernode-governor-hook',
    'principals' => ['Evernode Labs Pty Ltd'],
    'web' => 'https://evernode.org',
  ],

  //Evernode community

  'F6555895B65EAAF5F7947D923CA4BEE2CE2D1DBBE7ECB30CC0477598EC490A39' => [
    'title' => 'Evernode Price Oracle v1',
    'source' => 'https://github.com/Transia-RnD/evernode-oracle',
    'image' => 'https://secure.gravatar.com/avatar/1cc150400e735fc2bee2ed5e0f86619e',
    'principals' => ['Transia-RnD'],
  ],

  '0C581C5A109523E5E0238FF09296CFA5ED2FD3F7A111B5B9C0B143A50079F0A6' => [
    'title' => 'Evernode Price Oracle v2',
    'source' => 'https://github.com/Transia-RnD/evernode-oracle',
    'image' => 'https://secure.gravatar.com/avatar/1cc150400e735fc2bee2ed5e0f86619e',
    'principals' => ['Transia-RnD'],
  ],
  
  '800F279C5CE89D913CE7A1036D2585277840B0A28D474B405ECD341AD47400C9' => [
    'title' => 'Evernode Rewards Forwarder',
    'image' => null,
    'source' => 'https://github.com/MrKnowItAlll/SetEevernodeHook/tree/9b00016a880abdbfe85eb7a76e4e2aefa45b1b5b',
  ],
  '7839AC4D669F7560AEA6C5ACA1D28CDBB050CD24C126DE18D398A63791A95893' => [
    'title' => 'Evernode Rewards Forwarder (grosdarz.fr)',
    'image' => null,
    'source' => 'https://github.com/MrKnowItAlll/SetEevernodeHook/tree/9b00016a880abdbfe85eb7a76e4e2aefa45b1b5b',
  ],
  '0A0F42000EB040423A7D2439D8D60B9DC6A834792ACFDAFCEC1EEB5ACFB0C70D' => [
    'title' => 'Evernode Rewards Forwarder (lauryles59.ovh)',
    'image' => null,
    'source' => 'https://github.com/MrKnowItAlll/SetEevernodeHook/tree/9b00016a880abdbfe85eb7a76e4e2aefa45b1b5b',
  ],
  '701997A6F5961BFDA02B6726A6B5038462F7EE488BECFF79919458CFC8FECE53' => [
    'title' => 'Evernode Rewards Forwarder (evernodeau.com)',
    'image' => null,
    'source' => 'https://github.com/MrKnowItAlll/SetEevernodeHook/tree/9b00016a880abdbfe85eb7a76e4e2aefa45b1b5b',
  ],
  '5ACA29000DB42C666B8FC0BDCB271530E46A5FE0EB864BA456C95F74B026C11B' => [
    'title' => 'Evernode Rewards Forwarder (evergalaxy.net)',
    'image' => null,
    'source' => 'https://github.com/MrKnowItAlll/SetEevernodeHook/tree/9b00016a880abdbfe85eb7a76e4e2aefa45b1b5b',
  ],
  '2E8C0D9DEE2D331E58F8BA8813D0D39A99A08F634362D656AAC4F85715FF2872' => [
    'title' => 'Evernode Rewards Forwarder (crispynode.online, hstgr.cloud)',
    'image' => null,
    'source' => 'https://github.com/MrKnowItAlll/SetEevernodeHook/tree/9b00016a880abdbfe85eb7a76e4e2aefa45b1b5b',
  ],
  '9340272017CDA0DDEF8AB36A05588902406ED556170CD51F90F56505C8ED3C71' => [
    'title' => 'Evernode Rewards Forwarder 1 (evernode.eu)',
    'image' => null,
    'source' => 'https://github.com/MrKnowItAlll/SetEevernodeHook/tree/9b00016a880abdbfe85eb7a76e4e2aefa45b1b5b',
  ],
  '86657CC837B4CE78D0C1EDED4814E71E5EA63578BC7D5A15BBF2B169DE403BFA' => [
    'title' => 'Evernode Rewards Forwarder (spaceveg.space)',
    'image' => null,
    'source' => 'https://github.com/MrKnowItAlll/SetEevernodeHook/tree/9b00016a880abdbfe85eb7a76e4e2aefa45b1b5b',
  ],
  '1967221DC2033BF07FEB151654D6FB0ACA3691B818BA22CE81C5EDD79A9445F8' => [
    'title' => 'Evernode Rewards Forwarder',
    'image' => null,
    'source' => 'https://github.com/MrKnowItAlll/SetEevernodeHook/tree/9b00016a880abdbfe85eb7a76e4e2aefa45b1b5b',
  ],
  'AEBAF4E786F46E75876920851F55C7A5FBB6FE7FBFE6B463EAA9B9EBA893040E' => [
    'title' => 'Evernode Rewards Forwarder (rndblck.io)',
    'image' => null,
    'source' => 'https://github.com/MrKnowItAlll/SetEevernodeHook/tree/9b00016a880abdbfe85eb7a76e4e2aefa45b1b5b',
  ],
  '7FA3C1A059B977B82527FBFDFDBA657B9BBC6C722BBB8B6DD3D576336442FC00' => [
    'title' => 'Evernode Rewards Forwarder 2 (evernode.eu)',
    'image' => null,
    'source' => 'https://github.com/MrKnowItAlll/SetEevernodeHook/tree/9b00016a880abdbfe85eb7a76e4e2aefa45b1b5b',
  ],
  'A97957B08793B8D29FA13E5581BFA60285D43C04F615868C380EAC6A622EBDB0' => [
    'title' => 'Evernode Rewards Forwarder (vps.ovh.net)',
    'image' => null,
    'source' => 'https://github.com/MrKnowItAlll/SetEevernodeHook/tree/9b00016a880abdbfe85eb7a76e4e2aefa45b1b5b',
  ],
  '58D72B4AD05F994927862D20F85F97768C9002ABC1A716AA04F6235CE44C1E9D' => [
    'title' => 'Evernode Rewards Forwarder (grosdarz.fr)',
    'image' => null,
    'source' => 'https://github.com/MrKnowItAlll/SetEevernodeHook/tree/9b00016a880abdbfe85eb7a76e4e2aefa45b1b5b',
  ],
  'CF4BAD11E576A163EE95A3601B89455AA1943488B552B42E4FD4C2BB2392A4E7' => [
    'title' => 'Evernode Rewards Forwarder (1ever589node.site)',
    'image' => null,
    'source' => 'https://github.com/MrKnowItAlll/SetEevernodeHook/tree/9b00016a880abdbfe85eb7a76e4e2aefa45b1b5b',
  ],
  'D342CEE50C855C5B2EC40CD4CEC58939858327751E67CDE6E3ADB0C0F8A090FC' => [
    'title' => 'Evernode Rewards Forwarder (vps.ovh.net)',
    'image' => null,
    'source' => 'https://github.com/MrKnowItAlll/SetEevernodeHook/tree/9b00016a880abdbfe85eb7a76e4e2aefa45b1b5b',
  ],
  '3C60075C2F38C485632B3D25603830C9C7F4737A86F404F353D705407ED24445' => [
    'title' => 'Evernode Rewards Forwarder (guilastro.xyz)',
    'image' => null,
    'source' => 'https://github.com/MrKnowItAlll/SetEevernodeHook/tree/9b00016a880abdbfe85eb7a76e4e2aefa45b1b5b',
  ],

  '412E9084606862E7F8F6808098D4030FB98A4A22621C35FD67C1791A09F2A4DE' => [
    'title' => 'Evernode Rewards Redirect',
    'image' => null,
  ],
  'D3FBEE1F72A31ABA34F67C2B65AC96A7603BC4D19BF12502A02A847DB884650A' => [
    'title' => 'Evernode Rewards Redirect',
    'image' => null,
  ],
  '017FE7A5D7E2E98F96D7D9E7C8530A6065016FC2BB4E3B242A781F1BB4C8C1EE' => [
    'title' => 'Krazes hook',
    'image' => null,
  ],
  'EE287AFAF4E7BE1B93A06632F6E06580ACE4A2D8CB6B7CAD65EE1EE9657F3149' => [
    'title' => 'Evernode Rewards Redirect',
    'image' => null,
  ],
  'F77C439916E3FE5C8119743BA897E08BC687D661F992DE9EDDA80E4A1A47EEAA' => [
    'title' => 'Evernode Redirect',
    'image' => 'https://xwa-xahau.xrplwin.com/res/img/misc/hook_F77C439916E3FE5C8119743BA897E08BC687D661F992DE9EDDA80E4A1A47EEAA.webp',
    'source' => 'https://github.com/MrKnowItAlll/SetEevernodeHook/blob/master/redirect.c'
  ],

  '39FBA97BD995EA96CD9D94C16A7286A4CABB3490BAB8FA2A8AAA07291145102C' => [
    'title' => 'XRPLWin Guard',
    'principals' => ['XRPLWin'],
    'image' => null,
    'descr' => 'This hook rejects outgoing URITokenBuy transactions that have large amounts. Used for bridge protection for the Evernode Code IDE.',
    'web' => 'https://xahau.xrplwin.com/code/evernode',
  ],

  //Xahau Bet - Lottery hook combo by XRPL-Labs
  '2378A5711989FA84E7F0A810545A684C04FACFED022CB29E7B7DA48E422893CB' => [
    'title' => 'Xahau Bet - Lottery Router',
    'principals' => ['XRPL-Labs'],
    'web' => null,
    'image' => null,
  ],
  'B65311953CEA1781F5D05C3236A3655A9D79F2FE898EBF831739A0CA10017626' => [
    'title' => 'Xahau Bet - Lottery Starter',
    'principals' => ['XRPL-Labs'],
    'web' => null,
    'image' => null,
  ],
  '7A16488EBCDB2C16E2A733FF4FF524FDE1CFDB293CF352801C41CFE1A8B0231B' => [
    'title' => 'Xahau Bet - Lottery Engine',
    'principals' => ['XRPL-Labs'],
    'web' => null,
    'image' => null,
  ],
  'AE95FA0ABAD97CAF0A3722D78DD960289CA0295ABC4F4D283A70635C1B20616F' => [
    'title' => 'Xahau Bet - Lottery Finisher',
    'principals' => ['XRPL-Labs'],
    'web' => null,
    'image' => null,
  ],

  //Voucher
  '78753F1802C38D96286ACB9DB2E9CAA2A394ECBEBFA5D82537E61B38478DB146' => [
    'title' => 'Gift Voucher - Creator',
    'principals' => ['XRPL-Labs'],
    'web' => 'https://xaman.app',
    'image' => null,
  ],
  '9C83ABDEC707117F29E8E8D0BD16597D33147560939749B177AD102105522708' => [
    'title' => 'Gift Voucher - Claimer',
    'principals' => ['XRPL-Labs'],
    'web' => 'https://xaman.app',
    'image' => null,
  ],

  //Xahau Radio
  'D93D62E0F07E501891B37240C620446901A421D00BA45D9537EF432776E0C4BA' => [
    'title' => 'Xahau Radio',
    'image' => 'https://xwa-xahau.xrplwin.com/static/avatar/rh2i1UeXCCrv4RZdkpb1ioHtuNYghhhxmU.webp',
    'principals' => ['Ekiserrepé','Satish'],
    'source' => 'https://github.com/technotip/HookExamples/blob/main/ReturnChange/radio.c',
    'descr' => "For the 1st anniversary of @XahauNetwork I wanted to bring out this small project. It's an online radio playing lo-fi music that accepts payments on Xahau to keep playing music.\n\nTo play a song you need to send 1 XAH. You can choose between 100 songs by typing in the memo field the desired number, if you don't use the memo field, a song will be chosen randomly. If you want to play 10 random songs at a time, send 10 XAH, the memo field will be ignored.\n\nThe project uses a hook designed by @Satish_nl that simulates jukeboxes.\n\nSource: https://x.com/ekiserrepe/status/1852270395614720415",
  ],

  //VPRA Pet Battles on Xahau

  '2E67E67B7A9C4446403F67D8CA91716B54EE7F8FC48C15836133908616F41C10' => [
    'title' => 'VPRA Router',
    'image' => null,
    'principals' => ['Transia-RnD'],
    'source' => 'https://github.com/Transia-RnD/vpra-hooks/tree/main/contracts',
    'web' => 'https://vpra.app',
    //this is Denis's router hook, source of still unknown
  ],

  //missing battle

  'AF67797FCEBEAE6DAA9C1A7E9B4D8C8B79F1AA8032EBC2D30AB82D9FEBBC5CE9' => [
    'title' => 'VPRA Breed V2',
    'image' => null,
    'principals' => ['Transia-RnD'],
    'source' => 'https://github.com/Transia-RnD/vpra-hooks/blob/main/contracts/pet_breedV2.c',
    'web' => 'https://vpra.app',
  ],

  '6376E31EA396A96B35BB65689356050FE0C2EB3C0E0EC69B2045222BA8EB31CE' => [
    'title' => 'VPRA Mint V2',
    'image' => null,
    'principals' => ['Transia-RnD'],
    'source' => 'https://github.com/Transia-RnD/vpra-hooks/blob/main/contracts/pet_mintv2.c',
    'web' => 'https://vpra.app',
  ],

  '4F4D2D41E55B9D15BB24DCC0EEC62CB0B647E05774C7FCA704349C164B343B83' => [
    'title' => 'VPRA Race V2',
    'image' => null,
    'principals' => ['Transia-RnD'],
    'source' => 'https://github.com/Transia-RnD/vpra-hooks/blob/main/contracts/pet_raceV2.c',
    'web' => 'https://vpra.app',
  ],

  
  '6A7B23B8AF2C67113885D52CD960F5EE0C9000E2D257FB72038E704D188A1A9A' => [
    'title' => 'VPRA Race-Pool V2',
    'image' => null,
    'principals' => ['Transia-RnD'],
    'source' => 'https://github.com/Transia-RnD/vpra-hooks/blob/main/contracts/pet_race_poolV2.c',
    'web' => 'https://vpra.app',
  ],

  'DCC25F1DCE8916C25825082915A9B22E0C915B1CFB752DF77516076DA238B7A0' => [
    'title' => 'VPRA Update V2',
    'image' => null,
    'principals' => ['Transia-RnD'],
    'source' => 'https://github.com/Transia-RnD/vpra-hooks/blob/main/contracts/pet_updateV2.c',
    'web' => 'https://vpra.app',
  ],

  //TreasuryHooks (by satish)

  '11B6F1534186086EF95E297F64806D8E4231865EE9871A0C438EA8A51BE20BD8' => [
    'title' => 'Treasury Hook',
    'image' => 'https://xwa-xahau.xrplwin.com/res/img/misc/hook_11B6F1534186086EF95E297F64806D8E4231865EE9871A0C438EA8A51BE20BD8.webp',
    'principals' => ['XRPL-Labs'],
    'source' => 'https://github.com/Xahau/TreasuryHook',
    'web' => 'https://xahau-treasury.xrpl-labs.com',
    'descr' => "Voluntarily lock up the amount of XAH going out from the treasury account every set ledger interval.\n\nThe idea is to blackhole the treasury account, and the only way to withdraw funds is through the invoke transaction. Anybody can invoke the claim reward and the withdraw transactions on treasury account - after specified ledger interval.",
  ],

  'B55839E8CABBDE2501249C0F7B4BFD58FC25838AC79D6822594076804EABBE60' => [
    'title' => 'Treasury Hook - ClaimReward Forwarder',
    'image' => 'https://xwa-xahau.xrplwin.com/res/img/misc/hook_B55839E8CABBDE2501249C0F7B4BFD58FC25838AC79D6822594076804EABBE60.webp',
    'principals' => ['XRPL-Labs'],
    'source' => 'https://github.com/Xahau/TreasuryHook',
    'web' => 'https://xahau-treasury.xrpl-labs.com',
    'descr' => "Voluntarily lock up the amount of XAH going out from the treasury account every set ledger interval.\n\nThe idea is to blackhole the treasury account, and the only way to withdraw funds is through the invoke transaction. Anybody can invoke the claim reward and the withdraw transactions on treasury account - after specified ledger interval.\n\nWith this hook the claim reward is made possible and the claimed reward will be forwarded to the destination account immediately after the claim.",
  ],

  'D22582E8BAF59FC682DEF490A3992CADB3CD5CCE851FB358B2DE299ABE30DB9E' => [
    'title' => 'Ekiserrepe\'s Forwarder',
    'image' => 'https://xwa-xahau.xrplwin.com/res/img/misc/hook_D22582E8BAF59FC682DEF490A3992CADB3CD5CCE851FB358B2DE299ABE30DB9E.webp',
    'principals' => ['Ekiserrepe'],
    'source' => 'https://github.com/Ekiserrepe/forwarder-hook',
    'web' => null,
    'descr' => "This is a small example to demonstrate the use of a working hook in Xahau. The hook is programmed in C. It is recommended for educational purposes only. The creator is not responsible for any problems it may cause.\n\nThe hook is installed on an account. Once installed, every time the account receives a payment through a Payment or URITokenBuy transaction type, it will be distributed among the accounts stored in the account namespace. If there are no accounts in the namespace, it will do nothing.",
  ],

  
  //XRPLWin's voting hook
  '2346954D6EBA3C1CA0AFE0D9DFA164B10E985383927068BD3BE6B5194716B080' => [
    'title' => 'Simple Voting Contract',
    'image' => 'https://xwa-xahau.xrplwin.com/res/img/misc/hook_2346954D6EBA3C1CA0AFE0D9DFA164B10E985383927068BD3BE6B5194716B080.webp',
    'principals' => ['XRPLWin','Tequ'],
    'source' => 'https://github.com/XRPLWin/hook-simplevoting',
    'web' => 'https://xahau.xrplwin.com',
    'descr' => "This Voting Contract gives capabilities to host voting session.\n\nContract account can setup candidates,any account can vote (once) for candidate with Invoke transaction by sending desired Candidate ID.",
  ],

  //Docproof
  '5471643B1850CC673CCC7CEB7C4DD81B74F786F779CCBB8CCE634062BEAA4B01' => [
    'title' => 'Xahau Docproof Contract v1',
    'image' => 'https://xwa-xahau.xrplwin.com/res/img/misc/hook_5471643B1850CC673CCC7CEB7C4DD81B74F786F779CCBB8CCE634062BEAA4B01.webp',
    'principals' => ['Xahau DocProof','Andrei Rosseti'],
    'source' => 'https://github.com/rosseti/xahau-docproof',
    'web' => 'https://xahaudocproof.com',
    'descr' => "Streamline document workflows with secure blockchain signatures.\n\nTransform document workflows with cryptographically sealed, instantly verifiable digital signatures powered by cutting-edge blockchain technology.",
  ],

  'F6C82BF14D9083E22D371E2072AB845B5D4FAEF656B0D98A5976E2F9327D0F78' => [
    'title' => 'Xahau Docproof Contract v2',
    'image' => 'https://xwa-xahau.xrplwin.com/res/img/misc/hook_5471643B1850CC673CCC7CEB7C4DD81B74F786F779CCBB8CCE634062BEAA4B01.webp',
    'principals' => ['Xahau DocProof','Andrei Rosseti'],
    'source' => 'https://github.com/rosseti/xahau-docproof',
    'web' => 'https://xahaudocproof.com',
    'descr' => "Streamline document workflows with secure blockchain signatures.\n\nTransform document workflows with cryptographically sealed, instantly verifiable digital signatures powered by cutting-edge blockchain technology.",
  ],

  //Other
  '4512D7BABEF201C779E76B2FEECB0D655E088426B5769F0C6796A1E97FD82D91' => [
    'title' => 'Demo AMM Hook',
    'image' => null,
    'web' => null,
  ],

  '6CE65D856E1E38AE6DB1E864C973F1F3944898380AC769FA58560E507F961F7A' => [
    'title' => 'Xahau Bridge Proof-Of-Concept Hook',
    'image' => null,
    'principals' => ['Transia-RnD'],
    'descr' => 'https://twitter.com/angell_denis/status/1826617270552191040',
    'source' => 'https://github.com/Transia-RnD/xrpl-xahau-bridge',
    'web' => null,
  ],

  
];