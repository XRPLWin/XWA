# XRPL Orderbook Reader for PHP

This is PHP port of https://github.com/XRPL-Labs/XRPL-Orderbook-Reader

This repository takes XRPL Orderbook (`book_offers`) datasets and requested volume to
exchange and calculates the effective exchange rates based on the requested and available liquidity.

Optionally certain checks can be specified (eg. `book_offers` on the other side of the book)
to warn for limited (percentage) liquidity on the requested side, and possibly other side
of the order book.

## Requirements
- PHP 8.1 or higher
- [Composer](https://getcomposer.org/)

## Installation
This Package is still in **beta**, to install run

```
composer require TODO
```

