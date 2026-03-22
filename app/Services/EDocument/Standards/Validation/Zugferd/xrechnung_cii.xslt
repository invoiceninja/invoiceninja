<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<xsl:stylesheet xmlns:xs="http://www.w3.org/2001/XMLSchema"
                xmlns:xsd="http://www.w3.org/2001/XMLSchema"
                xmlns:saxon="http://saxon.sf.net/"
                xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
                xmlns:schold="http://www.ascc.net/xml/schematron"
                xmlns:iso="http://purl.oclc.org/dsdl/schematron"
                xmlns:xhtml="http://www.w3.org/1999/xhtml"
                xmlns:rsm="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100"
                xmlns:ccts="urn:un:unece:uncefact:documentation:standard:CoreComponentsTechnicalSpecification:2"
                xmlns:udt="urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100"
                xmlns:qdt="urn:un:unece:uncefact:data:standard:QualifiedDataType:100"
                xmlns:ram="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100"
                xmlns:u="utils"
                version="2.0"><!--Implementers: please note that overriding process-prolog or process-root is 
    the preferred method for meta-stylesheets to use where possible. -->
   <xsl:param name="archiveDirParameter"/>
   <xsl:param name="archiveNameParameter"/>
   <xsl:param name="fileNameParameter"/>
   <xsl:param name="fileDirParameter"/>
   <xsl:variable name="document-uri">
      <xsl:value-of select="document-uri(/)"/>
   </xsl:variable>
   <!--PHASES-->
   <!--PROLOG-->
   <xsl:output xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
               method="xml"
               omit-xml-declaration="no"
               standalone="yes"
               indent="yes"/>
   <!--XSD TYPES FOR XSLT2-->
   <!--KEYS AND FUNCTIONS-->
   <xsl:function xmlns="http://purl.oclc.org/dsdl/schematron"
                 as="xs:decimal"
                 name="u:decimalOrZero">
      <xsl:param name="element"/>
      <xsl:sequence select="if (boolean($element)) then xs:decimal($element) else 0"/>
   </xsl:function>
   <function xmlns="http://www.w3.org/1999/XSL/Transform"
             name="u:gln"
             as="xs:boolean">
      <param name="val"/>
      <variable name="length" select="string-length($val) - 1"/>
      <variable name="digits"
                select="reverse(for $i in string-to-codepoints(substring($val, 0, $length + 1)) return $i - 48)"/>
      <variable name="weightedSum"
                select="sum(for $i in (0 to $length - 1) return $digits[$i + 1] * (1 + ((($i + 1) mod 2) * 2)))"/>
      <value-of select="(10 - ($weightedSum mod 10)) mod 10 = number(substring($val, $length + 1, 1))"/>
   </function>
   <function xmlns="http://www.w3.org/1999/XSL/Transform"
             name="u:slack"
             as="xs:boolean">
      <param name="exp" as="xs:decimal"/>
      <param name="val" as="xs:decimal"/>
      <param name="slack" as="xs:decimal"/>
      <value-of select="xs:decimal($exp + $slack) &gt;= $val and xs:decimal($exp - $slack) &lt;= $val"/>
   </function>
   <function xmlns="http://www.w3.org/1999/XSL/Transform"
             name="u:mod11"
             as="xs:boolean">
      <param name="val"/>
      <variable name="length" select="string-length($val) - 1"/>
      <variable name="digits"
                select="reverse(for $i in string-to-codepoints(substring($val, 0, $length + 1)) return $i - 48)"/>
      <variable name="weightedSum"
                select="sum(for $i in (0 to $length - 1) return $digits[$i + 1] * (($i mod 6) + 2))"/>
      <value-of select="number($val) &gt; 0 and (11 - ($weightedSum mod 11)) mod 11 = number(substring($val, $length + 1, 1))"/>
   </function>
   <function xmlns="http://www.w3.org/1999/XSL/Transform"
             name="u:mod97-0208"
             as="xs:boolean">
      <param name="val"/>
      <variable name="checkdigits" select="substring($val,9,2)"/>
      <variable name="calculated_digits"
                select="xs:string(97 - (xs:integer(substring($val,1,8)) mod 97))"/>
      <value-of select="number($checkdigits) = number($calculated_digits)"/>
   </function>
   <function xmlns="http://www.w3.org/1999/XSL/Transform"
             name="u:checkCodiceIPA"
             as="xs:boolean">
      <param name="arg" as="xs:string?"/>
      <variable name="allowed-characters">ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789</variable>
      <sequence select="if ( (string-length(translate($arg, $allowed-characters, '')) = 0) and (string-length($arg) = 6) ) then true() else false()"/>
   </function>
   <function xmlns="http://www.w3.org/1999/XSL/Transform"
             name="u:checkCF"
             as="xs:boolean">
      <param name="arg" as="xs:string?"/>
      <sequence select="   if ( (string-length($arg) = 16) or (string-length($arg) = 11) )   then   (    if ((string-length($arg) = 16))    then    (     if (u:checkCF16($arg))     then     (      true()     )     else     (      false()     )    )    else    (     if(($arg castable as xs:integer)) then true() else false()     )   )   else   (    false()   )   "/>
   </function>
   <function xmlns="http://www.w3.org/1999/XSL/Transform"
             name="u:checkCF16"
             as="xs:boolean">
      <param name="arg" as="xs:string?"/>
      <variable name="allowed-characters">ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz</variable>
      <sequence select="     if (  (string-length(translate(substring($arg,1,6), $allowed-characters, '')) = 0) and       (substring($arg,7,2) castable as xs:integer) and       (string-length(translate(substring($arg,9,1), $allowed-characters, '')) = 0) and       (substring($arg,10,2) castable as xs:integer) and       (substring($arg,12,3) castable as xs:string) and       (substring($arg,15,1) castable as xs:integer) and       (string-length(translate(substring($arg,16,1), $allowed-characters, '')) = 0)      )     then true()     else false()     "/>
   </function>
   <function xmlns="http://www.w3.org/1999/XSL/Transform"
             name="u:checkPIVAseIT"
             as="xs:boolean">
      <param name="arg" as="xs:string"/>
      <variable name="paese" select="substring($arg,1,2)"/>
      <variable name="codice" select="substring($arg,3)"/>
      <sequence select="     if ( $paese = 'IT' or $paese = 'it' )    then    (     if ( ( string-length($codice) = 11 ) and ( if (u:checkPIVA($codice)!=0) then false() else true() ))     then     (      true()     )     else     (      false()     )    )    else    (     true()    )    "/>
   </function>
   <function xmlns="http://www.w3.org/1999/XSL/Transform"
             name="u:checkPIVA"
             as="xs:integer">
      <param name="arg" as="xs:string?"/>
      <sequence select="     if (not($arg castable as xs:integer))      then 1      else ( u:addPIVA($arg,xs:integer(0)) mod 10 )"/>
   </function>
   <function xmlns="http://www.w3.org/1999/XSL/Transform"
             name="u:addPIVA"
             as="xs:integer">
      <param name="arg" as="xs:string"/>
      <param name="pari" as="xs:integer"/>
      <variable name="tappo"
                select="if (not($arg castable as xs:integer)) then 0 else 1"/>
      <variable name="mapper"
                select="if ($tappo = 0) then 0 else                   ( if ($pari = 1)                    then ( xs:integer(substring('0246813579', ( xs:integer(substring($arg,1,1)) +1 ) ,1)) )                    else ( xs:integer(substring($arg,1,1) ) )                   )"/>
      <sequence select="if ($tappo = 0) then $mapper else ( xs:integer($mapper) + u:addPIVA(substring(xs:string($arg),2), (if($pari=0) then 1 else 0) ) )"/>
   </function>
   <function xmlns="http://www.w3.org/1999/XSL/Transform"
             name="u:abn"
             as="xs:boolean">
      <param name="val"/>
      <value-of select="( ((string-to-codepoints(substring($val,1,1)) - 49) * 10) + ((string-to-codepoints(substring($val,2,1)) - 48) * 1) + ((string-to-codepoints(substring($val,3,1)) - 48) * 3) + ((string-to-codepoints(substring($val,4,1)) - 48) * 5) + ((string-to-codepoints(substring($val,5,1)) - 48) * 7) + ((string-to-codepoints(substring($val,6,1)) - 48) * 9) + ((string-to-codepoints(substring($val,7,1)) - 48) * 11) + ((string-to-codepoints(substring($val,8,1)) - 48) * 13) + ((string-to-codepoints(substring($val,9,1)) - 48) * 15) + ((string-to-codepoints(substring($val,10,1)) - 48) * 17) + ((string-to-codepoints(substring($val,11,1)) - 48) * 19)) mod 89 = 0 "/>
   </function>
   <function xmlns="http://www.w3.org/1999/XSL/Transform"
             name="u:checkSEOrgnr"
             as="xs:boolean">
      <param name="number" as="xs:string"/>
      <choose>
         <when test="not(matches($number, '^\d+$'))">
            <sequence select="false()"/>
         </when>
         <otherwise>
            <variable name="mainPart" select="substring($number, 1, 9)"/>
            <variable name="checkDigit" select="substring($number, 10, 1)"/>
            <variable name="sum" as="xs:integer">
               <value-of select="sum(       for $pos in 1 to string-length($mainPart) return         if ($pos mod 2 = 1)         then (number(substring($mainPart, string-length($mainPart) - $pos + 1, 1)) * 2) mod 10 +           (number(substring($mainPart, string-length($mainPart) - $pos + 1, 1)) * 2) idiv 10         else number(substring($mainPart, string-length($mainPart) - $pos + 1, 1))      )"/>
            </variable>
            <variable name="calculatedCheckDigit" select="(10 - $sum mod 10) mod 10"/>
            <sequence select="$calculatedCheckDigit = number($checkDigit)"/>
         </otherwise>
      </choose>
   </function>
   <!--DEFAULT RULES-->
   <!--MODE: SCHEMATRON-SELECT-FULL-PATH-->
   <!--This mode can be used to generate an ugly though full XPath for locators-->
   <xsl:template match="*" mode="schematron-select-full-path">
      <xsl:apply-templates select="." mode="schematron-get-full-path-3"/>
   </xsl:template>
   <!--MODE: SCHEMATRON-FULL-PATH-->
   <!--This mode can be used to generate an ugly though full XPath for locators-->
   <xsl:template match="*" mode="schematron-get-full-path">
      <xsl:apply-templates select="parent::*" mode="schematron-get-full-path"/>
      <xsl:text>/</xsl:text>
      <xsl:choose>
         <xsl:when test="namespace-uri()=''">
            <xsl:value-of select="name()"/>
         </xsl:when>
         <xsl:otherwise>
            <xsl:text>*:</xsl:text>
            <xsl:value-of select="local-name()"/>
            <xsl:text>[namespace-uri()='</xsl:text>
            <xsl:value-of select="namespace-uri()"/>
            <xsl:text>']</xsl:text>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:variable name="preceding"
                    select="count(preceding-sibling::*[local-name()=local-name(current())                                   and namespace-uri() = namespace-uri(current())])"/>
      <xsl:text>[</xsl:text>
      <xsl:value-of select="1+ $preceding"/>
      <xsl:text>]</xsl:text>
   </xsl:template>
   <xsl:template match="@*" mode="schematron-get-full-path">
      <xsl:apply-templates select="parent::*" mode="schematron-get-full-path"/>
      <xsl:text>/</xsl:text>
      <xsl:choose>
         <xsl:when test="namespace-uri()=''">@<xsl:value-of select="name()"/>
         </xsl:when>
         <xsl:otherwise>
            <xsl:text>@*[local-name()='</xsl:text>
            <xsl:value-of select="local-name()"/>
            <xsl:text>' and namespace-uri()='</xsl:text>
            <xsl:value-of select="namespace-uri()"/>
            <xsl:text>']</xsl:text>
         </xsl:otherwise>
      </xsl:choose>
   </xsl:template>
   <!--MODE: SCHEMATRON-FULL-PATH-2-->
   <!--This mode can be used to generate prefixed XPath for humans-->
   <xsl:template match="node() | @*" mode="schematron-get-full-path-2">
      <xsl:for-each select="ancestor-or-self::*">
         <xsl:text>/</xsl:text>
         <xsl:value-of select="name(.)"/>
         <xsl:if test="preceding-sibling::*[name(.)=name(current())]">
            <xsl:text>[</xsl:text>
            <xsl:value-of select="count(preceding-sibling::*[name(.)=name(current())])+1"/>
            <xsl:text>]</xsl:text>
         </xsl:if>
      </xsl:for-each>
      <xsl:if test="not(self::*)">
         <xsl:text/>/@<xsl:value-of select="name(.)"/>
      </xsl:if>
   </xsl:template>
   <!--MODE: SCHEMATRON-FULL-PATH-3-->
   <!--This mode can be used to generate prefixed XPath for humans 
	(Top-level element has index)-->
   <xsl:template match="node() | @*" mode="schematron-get-full-path-3">
      <xsl:for-each select="ancestor-or-self::*">
         <xsl:text>/</xsl:text>
         <xsl:value-of select="name(.)"/>
         <xsl:if test="parent::*">
            <xsl:text>[</xsl:text>
            <xsl:value-of select="count(preceding-sibling::*[name(.)=name(current())])+1"/>
            <xsl:text>]</xsl:text>
         </xsl:if>
      </xsl:for-each>
      <xsl:if test="not(self::*)">
         <xsl:text/>/@<xsl:value-of select="name(.)"/>
      </xsl:if>
   </xsl:template>
   <!--MODE: GENERATE-ID-FROM-PATH -->
   <xsl:template match="/" mode="generate-id-from-path"/>
   <xsl:template match="text()" mode="generate-id-from-path">
      <xsl:apply-templates select="parent::*" mode="generate-id-from-path"/>
      <xsl:value-of select="concat('.text-', 1+count(preceding-sibling::text()), '-')"/>
   </xsl:template>
   <xsl:template match="comment()" mode="generate-id-from-path">
      <xsl:apply-templates select="parent::*" mode="generate-id-from-path"/>
      <xsl:value-of select="concat('.comment-', 1+count(preceding-sibling::comment()), '-')"/>
   </xsl:template>
   <xsl:template match="processing-instruction()" mode="generate-id-from-path">
      <xsl:apply-templates select="parent::*" mode="generate-id-from-path"/>
      <xsl:value-of select="concat('.processing-instruction-', 1+count(preceding-sibling::processing-instruction()), '-')"/>
   </xsl:template>
   <xsl:template match="@*" mode="generate-id-from-path">
      <xsl:apply-templates select="parent::*" mode="generate-id-from-path"/>
      <xsl:value-of select="concat('.@', name())"/>
   </xsl:template>
   <xsl:template match="*" mode="generate-id-from-path" priority="-0.5">
      <xsl:apply-templates select="parent::*" mode="generate-id-from-path"/>
      <xsl:text>.</xsl:text>
      <xsl:value-of select="concat('.',name(),'-',1+count(preceding-sibling::*[name()=name(current())]),'-')"/>
   </xsl:template>
   <!--MODE: GENERATE-ID-2 -->
   <xsl:template match="/" mode="generate-id-2">U</xsl:template>
   <xsl:template match="*" mode="generate-id-2" priority="2">
      <xsl:text>U</xsl:text>
      <xsl:number level="multiple" count="*"/>
   </xsl:template>
   <xsl:template match="node()" mode="generate-id-2">
      <xsl:text>U.</xsl:text>
      <xsl:number level="multiple" count="*"/>
      <xsl:text>n</xsl:text>
      <xsl:number count="node()"/>
   </xsl:template>
   <xsl:template match="@*" mode="generate-id-2">
      <xsl:text>U.</xsl:text>
      <xsl:number level="multiple" count="*"/>
      <xsl:text>_</xsl:text>
      <xsl:value-of select="string-length(local-name(.))"/>
      <xsl:text>_</xsl:text>
      <xsl:value-of select="translate(name(),':','.')"/>
   </xsl:template>
   <!--Strip characters-->
   <xsl:template match="text()" priority="-1"/>
   <!--SCHEMA SETUP-->
   <xsl:template match="/">
      <svrl:schematron-output xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                              title="Schematron Version 2.4.0 - XRechnung 3.0.2 compatible - CII"
                              schemaVersion="">
         <xsl:comment>
            <xsl:value-of select="$archiveDirParameter"/>   
		 <xsl:value-of select="$archiveNameParameter"/>  
		 <xsl:value-of select="$fileNameParameter"/>  
		 <xsl:value-of select="$fileDirParameter"/>
         </xsl:comment>
         <svrl:ns-prefix-in-attribute-values uri="urn:un:unece:uncefact:data:standard:CrossIndustryInvoice:100"
                                             prefix="rsm"/>
         <svrl:ns-prefix-in-attribute-values uri="urn:un:unece:uncefact:documentation:standard:CoreComponentsTechnicalSpecification:2"
                                             prefix="ccts"/>
         <svrl:ns-prefix-in-attribute-values uri="urn:un:unece:uncefact:data:standard:UnqualifiedDataType:100"
                                             prefix="udt"/>
         <svrl:ns-prefix-in-attribute-values uri="urn:un:unece:uncefact:data:standard:QualifiedDataType:100"
                                             prefix="qdt"/>
         <svrl:ns-prefix-in-attribute-values uri="urn:un:unece:uncefact:data:standard:ReusableAggregateBusinessInformationEntity:100"
                                             prefix="ram"/>
         <svrl:ns-prefix-in-attribute-values uri="utils" prefix="u"/>
         <svrl:active-pattern>
            <xsl:attribute name="document">
               <xsl:value-of select="document-uri(/)"/>
            </xsl:attribute>
            <xsl:attribute name="id">variable-pattern</xsl:attribute>
            <xsl:attribute name="name">variable-pattern</xsl:attribute>
            <xsl:apply-templates/>
         </svrl:active-pattern>
         <xsl:apply-templates select="/" mode="M14"/>
         <svrl:active-pattern>
            <xsl:attribute name="document">
               <xsl:value-of select="document-uri(/)"/>
            </xsl:attribute>
            <xsl:attribute name="id">peppol-cii-pattern-1</xsl:attribute>
            <xsl:attribute name="name">peppol-cii-pattern-1</xsl:attribute>
            <xsl:apply-templates/>
         </svrl:active-pattern>
         <xsl:apply-templates select="/" mode="M27"/>
         <svrl:active-pattern>
            <xsl:attribute name="document">
               <xsl:value-of select="document-uri(/)"/>
            </xsl:attribute>
            <xsl:attribute name="id">peppol-cii-pattern-0-a</xsl:attribute>
            <xsl:attribute name="name">peppol-cii-pattern-0-a</xsl:attribute>
            <xsl:apply-templates/>
         </svrl:active-pattern>
         <xsl:apply-templates select="/" mode="M28"/>
         <svrl:active-pattern>
            <xsl:attribute name="document">
               <xsl:value-of select="document-uri(/)"/>
            </xsl:attribute>
            <xsl:attribute name="id">peppol-cii-pattern-0-b</xsl:attribute>
            <xsl:attribute name="name">peppol-cii-pattern-0-b</xsl:attribute>
            <xsl:apply-templates/>
         </svrl:active-pattern>
         <xsl:apply-templates select="/" mode="M29"/>
         <svrl:active-pattern>
            <xsl:attribute name="document">
               <xsl:value-of select="document-uri(/)"/>
            </xsl:attribute>
            <xsl:attribute name="id">cii-pattern</xsl:attribute>
            <xsl:attribute name="name">cii-pattern</xsl:attribute>
            <xsl:apply-templates/>
         </svrl:active-pattern>
         <xsl:apply-templates select="/" mode="M30"/>
         <svrl:active-pattern>
            <xsl:attribute name="document">
               <xsl:value-of select="document-uri(/)"/>
            </xsl:attribute>
            <xsl:attribute name="id">cii-extension-pattern</xsl:attribute>
            <xsl:attribute name="name">cii-extension-pattern</xsl:attribute>
            <xsl:apply-templates/>
         </svrl:active-pattern>
         <xsl:apply-templates select="/" mode="M31"/>
         <svrl:active-pattern>
            <xsl:attribute name="document">
               <xsl:value-of select="document-uri(/)"/>
            </xsl:attribute>
            <xsl:attribute name="id">cii-cvd-pattern</xsl:attribute>
            <xsl:attribute name="name">cii-cvd-pattern</xsl:attribute>
            <xsl:apply-templates/>
         </svrl:active-pattern>
         <xsl:apply-templates select="/" mode="M32"/>
      </svrl:schematron-output>
   </xsl:template>
   <!--SCHEMATRON PATTERNS-->
   <svrl:text xmlns:svrl="http://purl.oclc.org/dsdl/svrl">Schematron Version 2.4.0 - XRechnung 3.0.2 compatible - CII</svrl:text>
   <xsl:param name="profile"
              select="             if (/rsm:CrossIndustryInvoice/rsm:ExchangedDocumentContext/ram:BusinessProcessSpecifiedDocumentContextParameter and matches(normalize-space(/rsm:CrossIndustryInvoice/rsm:ExchangedDocumentContext/ram:BusinessProcessSpecifiedDocumentContextParameter/ram:ID), 'urn:fdc:peppol.eu:2017:poacc:billing:([0-9]{2}):1.0')) then                 tokenize(normalize-space(/rsm:CrossIndustryInvoice/rsm:ExchangedDocumentContext/ram:BusinessProcessSpecifiedDocumentContextParameter/ram:ID), ':')[7]             else                 'Unknown'"/>
   <xsl:param name="supplierCountry"
              select="             if (/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction[1]/ram:ApplicableHeaderTradeAgreement[1]/ram:SellerTradeParty[1]/ram:SpecifiedTaxRegistration[ram:ID/@schemeID = 'VAT']/substring(ram:ID, 1, 2)) then                 upper-case(normalize-space(/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction[1]/ram:ApplicableHeaderTradeAgreement[1]/ram:SellerTradeParty[1]/ram:SpecifiedTaxRegistration[ram:ID/@schemeID = 'VAT']/substring(ram:ID, 1, 2)))             else                 if (/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTaxRepresentativeTradeParty/ram:SpecifiedTaxRegistration[ram:ID/@schemeID = 'VAT']/substring(ram:ID, 1, 2)) then                     upper-case(normalize-space(/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTaxRepresentativeTradeParty/ram:SpecifiedTaxRegistration[ram:ID/@schemeID = 'VAT']/substring(ram:ID, 1, 2)))                 else                     if (/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:PostalTradeAddress/ram:CountryID) then                         upper-case(normalize-space(/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:PostalTradeAddress/ram:CountryID))                     else                         'XX'"/>
   <xsl:param name="documentCurrencyCode"
              select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:InvoiceCurrencyCode"/>
   <xsl:param name="slackValue"
              select="if($documentCurrencyCode = 'HUF') then 0.5 else 0.02"/>
   <xsl:param name="taxCurrencyCode"
              select="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:TaxCurrencyCode"/>
   <!--PATTERN variable-pattern-->
   <xsl:variable name="XR-MAJOR-MINOR-VERSION" select="'3.0'"/>
   <xsl:variable name="CVD-MAJOR-MINOR-VERSION" select="'0.9'"/>
   <xsl:variable name="XR-CIUS-ID"
                 select="concat('urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_', $XR-MAJOR-MINOR-VERSION )"/>
   <xsl:variable name="XR-EXTENSION-ID"
                 select="concat($XR-CIUS-ID, '#conformant#urn:xeinkauf.de:kosit:extension:xrechnung_' ,$XR-MAJOR-MINOR-VERSION )"/>
   <xsl:variable name="XR-CVD-ID"
                 select="concat($XR-CIUS-ID, '#compliant#urn:xeinkauf.de:kosit:xrechnung:cvd_' , $CVD-MAJOR-MINOR-VERSION )"/>
   <xsl:variable name="XR-SKONTO-REGEX"
                 select="'(^|\r?\n)#(SKONTO)#TAGE=([0-9]+#PROZENT=[0-9]+\.[0-9]{2})(#BASISBETRAG=-?[0-9]+\.[0-9]{2})?#$'"/>
   <xsl:variable name="XR-EMAIL-REGEX" select="'^[^@\s]+@([^@.\s]+\.)+[^@.\s]+$'"/>
   <xsl:variable name="XR-TELEPHONE-REGEX" select="'.*([0-9].*){3,}.*'"/>
   <xsl:variable name="XR-URL-REGEX" select="'^([a-zA-Z])([a-zA-Z0-9+.-])+:.*'"/>
   <xsl:variable name="DIGA-CODES" select="' XR01 XR02 XR03 '"/>
   <xsl:variable name="ISO-6523-ICD-CODES"
                 select="' 0002 0003 0004 0005 0006 0007 0008 0009 0010 0011 0012 0013 0014 0015 0016 0017 0018 0019 0020 0021 0022 0023 0024 0025 0026 0027 0028 0029 0030 0031 0032 0033 0034 0035 0036 0037 0038 0039 0040 0041 0042 0043 0044 0045 0046 0047 0048 0049 0050 0051 0052 0053 0054 0055 0056 0057 0058 0059 0060 0061 0062 0063 0064 0065 0066 0067 0068 0069 0070 0071 0072 0073 0074 0075 0076 0077 0078 0079 0080 0081 0082 0083 0084 0085 0086 0087 0088 0089 0090 0091 0093 0094 0095 0096 0097 0098 0099 0100 0101 0102 0104 0105 0106 0107 0108 0109 0110 0111 0112 0113 0114 0115 0116 0117 0118 0119 0120 0121 0122 0123 0124 0125 0126 0127 0128 0129 0130 0131 0132 0133 0134 0135 0136 0137 0138 0139 0140 0141 0142 0143 0144 0145 0146 0147 0148 0149 0150 0151 0152 0153 0154 0155 0156 0157 0158 0159 0160 0161 0162 0163 0164 0165 0166 0167 0168 0169 0170 0171 0172 0173 0174 0175 0176 0177 0178 0179 0180 0183 0184 0185 0186 0187 0188 0189 0190 0191 0192 0193 0194 0195 0196 0197 0198 0199 0200 0201 0202 0203 0204 0205 0206 0207 0208 0209 0210 0211 0212 0213 0214 0215 0216 0217 0218 0219 0220 0221 0222 0223 0224 0225 0226 0227 0228 0229 0230 0231 0232 0233 0234 0235 0236 0237 0238 0239 0240'"/>
   <xsl:variable name="ISO-6523-ICD-EXT-CODES"
                 select="concat($DIGA-CODES, $ISO-6523-ICD-CODES)"/>
   <xsl:variable name="CEF-EAS-CODES"
                 select="' 0002 0007 0009 0037 0060 0088 0096 0097 0106 0130 0135 0142 0147 0151 0154 0158 0170 0177 0183 0184 0188 0190 0191 0192 0193 0194 0195 0196 0198 0199 0200 0201 0202 0203 0204 0205 0208 0209 0210 0211 0212 0213 0215 0216 0217 0218 0219 0220 0221 0225 0230 0240 0235 9910 9913 9914 9915 9918 9919 9920 9922 9923 9924 9925 9926 9927 9928 9929 9930 9931 9932 9933 9934 9935 9936 9937 9938 9939 9940 9941 9942 9943 9944 9945 9946 9947 9948 9949 9950 9951 9952 9953 9957 9959 AN AQ AS AU EM '"/>
   <xsl:variable name="CEF-EAS-EXT-CODES" select="concat($DIGA-CODES, $CEF-EAS-CODES)"/>
   <xsl:variable name="CVD-CODE" select="' CVD '"/>
   <xsl:variable name="UNTDID-7143-CODES"
                 select="' AA AB AC AD AE AF AG AH AI AJ AK AL AM AN AO AP AQ AR AS AT AU AV AW AX AY AZ BA BB BC BD BE BF BG BH BI BJ BK BL BM BN BO BP BQ BR BS BT BU BV BW BX BY BZ CC CG CL CR CV DR DW EC EF EMD EN FS GB GN GMN GS HS IB IN IS IT IZ MA MF MN MP NB ON PD PL PO PPI PV QS RC RN RU RY SA SG SK SN SRS SRT SRU SRV SRW SRX SRY SRZ SS SSA SSB SSC SSD SSE SSF SSG SSH SSI SSJ SSK SSL SSM SSN SSO SSP SSQ SSR SSS SST SSU SSV SSW SSX SSY SSZ ST STA STB STC STD STE STF STG STH STI STJ STK STL STM STN STO STP STQ STR STS STT STU STV STW STX STY STZ SUA SUB SUC SUD SUE SUF SUG SUH SUI SUJ SUK SUL SUM TG TSN TSO TSP TSQ TSR TSS TST TSU UA UP VN VP VS VX ZZZ '"/>
   <xsl:variable name="UNTDID-7143-CVD-CODES"
                 select="concat($CVD-CODE, $UNTDID-7143-CODES)"/>
   <xsl:variable name="CVD-VEHICLE-CATEGORY"
                 select="('M1', 'M2', 'M3', 'N1', 'N2', 'N3')"/>
   <xsl:variable name="CVA-CODES" select="('clean', 'zero-emission', 'other')"/>
   <xsl:template match="text()" priority="-1" mode="M14"/>
   <xsl:template match="@*|node()" priority="-2" mode="M14">
      <xsl:apply-templates select="*" mode="M14"/>
   </xsl:template>
   <!--PATTERN peppol-cii-pattern-1-->
   <!--RULE -->
   <xsl:template match="rsm:ExchangedDocumentContext" priority="1013" mode="M27">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                       context="rsm:ExchangedDocumentContext"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="ram:BusinessProcessSpecifiedDocumentContextParameter/ram:ID"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="ram:BusinessProcessSpecifiedDocumentContextParameter/ram:ID">
               <xsl:attribute name="id">PEPPOL-EN16931-R001</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>Business process MUST be provided.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M27"/>
   </xsl:template>
   <!--RULE -->
   <xsl:template match="ram:ApplicableHeaderTradeSettlement"
                 priority="1012"
                 mode="M27">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                       context="ram:ApplicableHeaderTradeSettlement"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="not(ram:TaxCurrencyCode) or normalize-space(ram:TaxCurrencyCode/text()) != normalize-space(ram:InvoiceCurrencyCode/text())"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="not(ram:TaxCurrencyCode) or normalize-space(ram:TaxCurrencyCode/text()) != normalize-space(ram:InvoiceCurrencyCode/text())">
               <xsl:attribute name="id">PEPPOL-EN16931-R005</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>VAT accounting currency code MUST be different from invoice currency code when provided.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="count(ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount[@currencyID = $documentCurrencyCode]) &lt;=1"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="count(ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount[@currencyID = $documentCurrencyCode]) &lt;=1">
               <xsl:attribute name="id">PEPPOL-EN16931-R053</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>No more than one tax total amount must be provided where currency id equals document currency code.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="                     count(ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount[@currencyID != $documentCurrencyCode]) = (if (ram:TaxCurrencyCode) then                         1                     else                         0)"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="count(ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount[@currencyID != $documentCurrencyCode]) = (if (ram:TaxCurrencyCode) then 1 else 0)">
               <xsl:attribute name="id">PEPPOL-EN16931-R054</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>Only one tax total amount must be provided where currency id equals tax currency code, if tax currency code (BT-6) is provided.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="not(/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:TaxCurrencyCode and ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount[@currencyID = $documentCurrencyCode]) or (ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount[@currencyID = $taxCurrencyCode] &lt; 0 and ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount[@currencyID = $documentCurrencyCode] &lt; 0) or (ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount[@currencyID = $taxCurrencyCode] &gt;= 0 and ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount[@currencyID = $documentCurrencyCode] &gt;= 0)"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="not(/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:TaxCurrencyCode and ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount[@currencyID = $documentCurrencyCode]) or (ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount[@currencyID = $taxCurrencyCode] &lt; 0 and ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount[@currencyID = $documentCurrencyCode] &lt; 0) or (ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount[@currencyID = $taxCurrencyCode] &gt;= 0 and ram:SpecifiedTradeSettlementHeaderMonetarySummation/ram:TaxTotalAmount[@currencyID = $documentCurrencyCode] &gt;= 0)">
               <xsl:attribute name="id">PEPPOL-EN16931-R055</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>Invoice total VAT amount and Invoice total VAT amount in accounting currency MUST have the same operational sign</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M27"/>
   </xsl:template>
   <!--RULE -->
   <xsl:template match="ram:BuyerTradeParty" priority="1011" mode="M27">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl" context="ram:BuyerTradeParty"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="ram:URIUniversalCommunication/ram:URIID"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="ram:URIUniversalCommunication/ram:URIID">
               <xsl:attribute name="id">PEPPOL-EN16931-R010</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>Buyer electronic address MUST be provided</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M27"/>
   </xsl:template>
   <!--RULE -->
   <xsl:template match="ram:SellerTradeParty" priority="1010" mode="M27">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl" context="ram:SellerTradeParty"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="ram:URIUniversalCommunication/ram:URIID"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="ram:URIUniversalCommunication/ram:URIID">
               <xsl:attribute name="id">PEPPOL-EN16931-R020</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>Seller electronic address MUST be provided</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M27"/>
   </xsl:template>
   <!--RULE -->
   <xsl:template match="ram:SpecifiedTradeAllowanceCharge[ram:CalculationPercent and not(ram:BasisAmount)]"
                 priority="1009"
                 mode="M27">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                       context="ram:SpecifiedTradeAllowanceCharge[ram:CalculationPercent and not(ram:BasisAmount)]"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="false()"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl" test="false()">
               <xsl:attribute name="id">PEPPOL-EN16931-R041</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>Allowance/charge base
                amount MUST be provided when allowance/charge percentage is provided.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M27"/>
   </xsl:template>
   <!--RULE -->
   <xsl:template match="ram:SpecifiedTradeAllowanceCharge[not(ram:CalculationPercent) and ram:BasisAmount]"
                 priority="1008"
                 mode="M27">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                       context="ram:SpecifiedTradeAllowanceCharge[not(ram:CalculationPercent) and ram:BasisAmount]"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="false()"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl" test="false()">
               <xsl:attribute name="id">PEPPOL-EN16931-R042</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>Allowance/charge percentage
                MUST be provided when allowance/charge base amount is provided.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M27"/>
   </xsl:template>
   <!--RULE -->
   <xsl:template match="ram:SpecifiedTradeAllowanceCharge"
                 priority="1007"
                 mode="M27">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                       context="ram:SpecifiedTradeAllowanceCharge"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="not(ram:CalculationPercent and ram:BasisAmount) or u:slack(if (ram:ActualAmount) then ram:ActualAmount else 0, (xs:decimal(ram:BasisAmount) * xs:decimal(ram:CalculationPercent)) div 100, $slackValue)"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="not(ram:CalculationPercent and ram:BasisAmount) or u:slack(if (ram:ActualAmount) then ram:ActualAmount else 0, (xs:decimal(ram:BasisAmount) * xs:decimal(ram:CalculationPercent)) div 100, $slackValue)">
               <xsl:attribute name="id">PEPPOL-EN16931-R040</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>Allowance/charge amount must equal base amount * percentage/100 if base amount and percentage exists</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="normalize-space(ram:ChargeIndicator/udt:Indicator/text()) = 'true' or normalize-space(ram:ChargeIndicator/udt:Indicator/text()) = 'false'"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="normalize-space(ram:ChargeIndicator/udt:Indicator/text()) = 'true' or normalize-space(ram:ChargeIndicator/udt:Indicator/text()) = 'false'">
               <xsl:attribute name="id">PEPPOL-EN16931-R043-1</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>Allowance/charge ChargeIndicator value MUST equal 'true' or 'false'</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M27"/>
   </xsl:template>
   <!--RULE -->
   <xsl:template match="ram:AppliedTradeAllowanceCharge" priority="1006" mode="M27">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                       context="ram:AppliedTradeAllowanceCharge"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="normalize-space(ram:ChargeIndicator/udt:Indicator/text()) = 'true' or normalize-space(ram:ChargeIndicator/udt:Indicator/text()) = 'false'"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="normalize-space(ram:ChargeIndicator/udt:Indicator/text()) = 'true' or normalize-space(ram:ChargeIndicator/udt:Indicator/text()) = 'false'">
               <xsl:attribute name="id">PEPPOL-EN16931-R043-2</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>Allowance/charge ChargeIndicator value MUST equal 'true' or 'false'</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M27"/>
   </xsl:template>
   <!--RULE -->
   <xsl:template match="                 ram:SpecifiedTradeSettlementPaymentMeans[some $code in tokenize('49 59', '\s')                     satisfies normalize-space(ram:TypeCode) = $code]"
                 priority="1005"
                 mode="M27">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                       context="                 ram:SpecifiedTradeSettlementPaymentMeans[some $code in tokenize('49 59', '\s')                     satisfies normalize-space(ram:TypeCode) = $code]"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="../ram:SpecifiedTradePaymentTerms/ram:DirectDebitMandateID"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="../ram:SpecifiedTradePaymentTerms/ram:DirectDebitMandateID">
               <xsl:attribute name="id">PEPPOL-EN16931-R061</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>Mandate reference MUST be provided for direct debit.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M27"/>
   </xsl:template>
   <!--RULE -->
   <xsl:template match="rsm:SupplyChainTradeTransaction[ram:ApplicableHeaderTradeSettlement/ram:BillingSpecifiedPeriod/ram:StartDateTime]/ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedLineTradeSettlement/ram:BillingSpecifiedPeriod/ram:StartDateTime"
                 priority="1004"
                 mode="M27">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                       context="rsm:SupplyChainTradeTransaction[ram:ApplicableHeaderTradeSettlement/ram:BillingSpecifiedPeriod/ram:StartDateTime]/ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedLineTradeSettlement/ram:BillingSpecifiedPeriod/ram:StartDateTime"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="udt:DateTimeString &gt;= ../../../../ram:ApplicableHeaderTradeSettlement/ram:BillingSpecifiedPeriod/ram:StartDateTime/udt:DateTimeString"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="udt:DateTimeString &gt;= ../../../../ram:ApplicableHeaderTradeSettlement/ram:BillingSpecifiedPeriod/ram:StartDateTime/udt:DateTimeString">
               <xsl:attribute name="id">PEPPOL-EN16931-R110</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>Start date of line period MUST be within invoice period.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M27"/>
   </xsl:template>
   <!--RULE -->
   <xsl:template match="rsm:SupplyChainTradeTransaction[ram:ApplicableHeaderTradeSettlement/ram:BillingSpecifiedPeriod/ram:EndDateTime]/ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedLineTradeSettlement/ram:BillingSpecifiedPeriod/ram:EndDateTime"
                 priority="1003"
                 mode="M27">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                       context="rsm:SupplyChainTradeTransaction[ram:ApplicableHeaderTradeSettlement/ram:BillingSpecifiedPeriod/ram:EndDateTime]/ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedLineTradeSettlement/ram:BillingSpecifiedPeriod/ram:EndDateTime"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="udt:DateTimeString &lt;= ../../../../ram:ApplicableHeaderTradeSettlement/ram:BillingSpecifiedPeriod/ram:EndDateTime/udt:DateTimeString"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="udt:DateTimeString &lt;= ../../../../ram:ApplicableHeaderTradeSettlement/ram:BillingSpecifiedPeriod/ram:EndDateTime/udt:DateTimeString">
               <xsl:attribute name="id">PEPPOL-EN16931-R111</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>End date of line period MUST be within invoice period.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M27"/>
   </xsl:template>
   <!--RULE -->
   <xsl:template match="ram:IncludedSupplyChainTradeLineItem"
                 priority="1002"
                 mode="M27">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                       context="ram:IncludedSupplyChainTradeLineItem"/>
      <xsl:variable name="lineExtensionAmount"
                    select="                     if (ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeSettlementLineMonetarySummation/ram:LineTotalAmount) then                         xs:decimal(ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeSettlementLineMonetarySummation/ram:LineTotalAmount)                     else                         0"/>
      <xsl:variable name="quantity"
                    select="                     if (ram:SpecifiedLineTradeDelivery/ram:BilledQuantity) then                         xs:decimal(ram:SpecifiedLineTradeDelivery/ram:BilledQuantity)                     else                         1"/>
      <xsl:variable name="priceAmount"
                    select="                     if (ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice/ram:ChargeAmount) then                         xs:decimal(ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice/ram:ChargeAmount)                     else                         0"/>
      <xsl:variable name="baseQuantity"
                    select="                     if (ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice/ram:BasisQuantity and xs:decimal(ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice/ram:BasisQuantity) != 0) then                         xs:decimal(ram:SpecifiedLineTradeAgreement/ram:NetPriceProductTradePrice/ram:BasisQuantity)                     else                         1"/>
      <xsl:variable name="allowancesTotal"
                    select="                     if (ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeAllowanceCharge[normalize-space(ram:ChargeIndicator/udt:Indicator) = 'false']) then                         round(sum(ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeAllowanceCharge[normalize-space(ram:ChargeIndicator/udt:Indicator) = 'false']/ram:ActualAmount/xs:decimal(.)) * 10 * 10) div 100                     else                         0"/>
      <xsl:variable name="chargesTotal"
                    select="                     if (ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeAllowanceCharge[normalize-space(ram:ChargeIndicator/udt:Indicator) = 'true']) then                         round(sum(ram:SpecifiedLineTradeSettlement/ram:SpecifiedTradeAllowanceCharge[normalize-space(ram:ChargeIndicator/udt:Indicator) = 'true']/ram:ActualAmount/xs:decimal(.)) * 10 * 10) div 100                     else                         0"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="(not(ram:SpecifiedLineTradeSettlement/ram:AdditionalReferencedDocument) or (ram:SpecifiedLineTradeSettlement/ram:AdditionalReferencedDocument/ram:TypeCode='130'))"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="(not(ram:SpecifiedLineTradeSettlement/ram:AdditionalReferencedDocument) or (ram:SpecifiedLineTradeSettlement/ram:AdditionalReferencedDocument/ram:TypeCode='130'))">
               <xsl:attribute name="id">PEPPOL-EN16931-R101</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>Element Additional referenced document can only be used for Invoice line object.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M27"/>
   </xsl:template>
   <!--RULE -->
   <xsl:template match="ram:NetPriceProductTradePrice | ram:GrossPriceProductTradePrice"
                 priority="1001"
                 mode="M27">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                       context="ram:NetPriceProductTradePrice | ram:GrossPriceProductTradePrice"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="not(ram:BasisQuantity) or xs:decimal(ram:BasisQuantity) &gt; 0"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="not(ram:BasisQuantity) or xs:decimal(ram:BasisQuantity) &gt; 0">
               <xsl:attribute name="id">PEPPOL-EN16931-R121</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>Base quantity MUST be a positive number above zero.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M27"/>
   </xsl:template>
   <!--RULE -->
   <xsl:template match="ram:NetPriceProductTradePrice/ram:BasisQuantity[@unitCode] | ram:GrossPriceProductTradePrice/ram:BasisQuantity[@unitCode]"
                 priority="1000"
                 mode="M27">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                       context="ram:NetPriceProductTradePrice/ram:BasisQuantity[@unitCode] | ram:GrossPriceProductTradePrice/ram:BasisQuantity[@unitCode]"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="@unitCode = ../../../ram:SpecifiedLineTradeDelivery/ram:BilledQuantity/@unitCode"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="@unitCode = ../../../ram:SpecifiedLineTradeDelivery/ram:BilledQuantity/@unitCode">
               <xsl:attribute name="id">PEPPOL-EN16931-R130</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>Unit code of price base quantity MUST be same as invoiced quantity.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M27"/>
   </xsl:template>
   <xsl:template match="text()" priority="-1" mode="M27"/>
   <xsl:template match="@*|node()" priority="-2" mode="M27">
      <xsl:apply-templates select="*" mode="M27"/>
   </xsl:template>
   <!--PATTERN peppol-cii-pattern-0-a-->
   <!--RULE -->
   <xsl:template match="//*[not(name() = 'ram:ApplicableHeaderTradeDelivery') and not(*) and not(normalize-space())]"
                 priority="1000"
                 mode="M28">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                       context="//*[not(name() = 'ram:ApplicableHeaderTradeDelivery') and not(*) and not(normalize-space())]"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="false()"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl" test="false()">
               <xsl:attribute name="id">PEPPOL-EN16931-R008</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>Document MUST not contain empty elements.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M28"/>
   </xsl:template>
   <xsl:template match="text()" priority="-1" mode="M28"/>
   <xsl:template match="@*|node()" priority="-2" mode="M28">
      <xsl:apply-templates select="*" mode="M28"/>
   </xsl:template>
   <!--PATTERN peppol-cii-pattern-0-b-->
   <!--RULE -->
   <xsl:template match="rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedLineTradeAgreement/ram:GrossPriceProductTradePrice"
                 priority="1000"
                 mode="M29">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                       context="rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedLineTradeAgreement/ram:GrossPriceProductTradePrice"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="not(ram:AppliedTradeAllowanceCharge/ram:ActualAmount) or ram:AppliedTradeAllowanceCharge/ram:ChargeIndicator/udt:Indicator = 'false'"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="not(ram:AppliedTradeAllowanceCharge/ram:ActualAmount) or ram:AppliedTradeAllowanceCharge/ram:ChargeIndicator/udt:Indicator = 'false'">
               <xsl:attribute name="id">PEPPOL-EN16931-R044</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>Charge on price level is NOT allowed. Only value 'false' allowed.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="not(ram:ChargeAmount) or xs:decimal(../ram:NetPriceProductTradePrice/ram:ChargeAmount) = xs:decimal(ram:ChargeAmount) - u:decimalOrZero(ram:AppliedTradeAllowanceCharge/ram:ActualAmount[1])"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="not(ram:ChargeAmount) or xs:decimal(../ram:NetPriceProductTradePrice/ram:ChargeAmount) = xs:decimal(ram:ChargeAmount) - u:decimalOrZero(ram:AppliedTradeAllowanceCharge/ram:ActualAmount[1])">
               <xsl:attribute name="id">PEPPOL-EN16931-R046</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>Item net price MUST equal (Gross price - Allowance amount) when gross price is provided.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M29"/>
   </xsl:template>
   <xsl:template match="text()" priority="-1" mode="M29"/>
   <xsl:template match="@*|node()" priority="-2" mode="M29">
      <xsl:apply-templates select="*" mode="M29"/>
   </xsl:template>
   <!--PATTERN cii-pattern-->
   <!--RULE -->
   <xsl:template match="/rsm:CrossIndustryInvoice" priority="1013" mode="M30">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                       context="/rsm:CrossIndustryInvoice"/>
      <xsl:variable name="BT-89-path"
                    select="rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradePaymentTerms/ram:DirectDebitMandateID"/>
      <xsl:variable name="BT-90-path"
                    select="rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:CreditorReferenceID"/>
      <xsl:variable name="BT-91-path"
                    select="rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementPaymentMeans/ram:PayerPartyDebtorFinancialAccount/ram:IBANID"/>
      <xsl:variable name="BG-19-not-existing"
                    select="not(exists(($BT-89-path, $BT-90-path, $BT-91-path)))"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="(($BT-89-path or $BT-91-path) and $BT-90-path) or $BG-19-not-existing"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="(($BT-89-path or $BT-91-path) and $BT-90-path) or $BG-19-not-existing">
               <xsl:attribute name="id">BR-DE-30</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>[BR-DE-30] Wenn "DIRECT DEBIT" BG-19 vorhanden ist, dann muss "Bank assigned creditor identifier" BT-90 übermittelt werden.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="(($BT-89-path or $BT-90-path) and $BT-91-path) or $BG-19-not-existing"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="(($BT-89-path or $BT-90-path) and $BT-91-path) or $BG-19-not-existing">
               <xsl:attribute name="id">BR-DE-31</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>[BR-DE-31] Wenn "DIRECT DEBIT" BG-19 vorhanden ist, dann muss "Debited account identifier" BT-91 übermittelt werden.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementPaymentMeans"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementPaymentMeans">
               <xsl:attribute name="id">BR-DE-1</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>[BR-DE-1] Eine Rechnung (INVOICE) muss Angaben zu "PAYMENT INSTRUCTIONS" (BG-16) enthalten.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:BuyerReference[boolean(normalize-space(.))]"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:BuyerReference[boolean(normalize-space(.))]">
               <xsl:attribute name="id">BR-DE-15</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>[BR-DE-15] Das Element "Buyer reference" (BT-10) muss übermittelt werden.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="not((rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax/ram:TypeCode = 'VAT' and                          rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax/ram:CategoryCode = ('S', 'Z', 'E', 'AE', 'K', 'G', 'L', 'M')) or                         (rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeAllowanceCharge/ram:CategoryTradeTax = 'VAT' and                          rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeAllowanceCharge/ram:CategoryTradeTax/ram:CategoryCode = ('S', 'Z', 'E', 'AE', 'K', 'G', 'L', 'M')) or                         (rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax/ram:TypeCode = 'VAT' and                          rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax/ram:CategoryCode = ('S', 'Z', 'E', 'AE', 'K', 'G', 'L', 'M'))) or                     ((rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:SpecifiedTaxRegistration/ram:ID[normalize-space(@schemeID)='VA' or                                                                                                                                                   normalize-space(@schemeID)='FC'][boolean(normalize-space(.))], rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTaxRepresentativeTradeParty))"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="not((rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax/ram:TypeCode = 'VAT' and rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax/ram:CategoryCode = ('S', 'Z', 'E', 'AE', 'K', 'G', 'L', 'M')) or (rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeAllowanceCharge/ram:CategoryTradeTax = 'VAT' and rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeAllowanceCharge/ram:CategoryTradeTax/ram:CategoryCode = ('S', 'Z', 'E', 'AE', 'K', 'G', 'L', 'M')) or (rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax/ram:TypeCode = 'VAT' and rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedLineTradeSettlement/ram:ApplicableTradeTax/ram:CategoryCode = ('S', 'Z', 'E', 'AE', 'K', 'G', 'L', 'M'))) or ((rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:SpecifiedTaxRegistration/ram:ID[normalize-space(@schemeID)='VA' or normalize-space(@schemeID)='FC'][boolean(normalize-space(.))], rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTaxRepresentativeTradeParty))">
               <xsl:attribute name="id">BR-DE-16</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>[BR-DE-16] Wenn in einer Rechnung die Steuercodes S, Z, E, AE, K, G, L oder M verwendet werden, muss mindestens eines der Elemente "Seller VAT identifier" (BT-31), "Seller tax registration identifier" (BT-32)
          oder "SELLER TAX REPRESENTATIVE PARTY" (BG-11) übermittelt werden.
      </svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="normalize-space(rsm:ExchangedDocument/ram:TypeCode) = ('326', '380', '384', '389', '381', '875', '876', '877')"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="normalize-space(rsm:ExchangedDocument/ram:TypeCode) = ('326', '380', '384', '389', '381', '875', '876', '877')">
               <xsl:attribute name="id">BR-DE-17</xsl:attribute>
               <xsl:attribute name="flag">warning</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>[BR-DE-17] Mit dem Element "Invoice type code" (BT-3) sollen ausschließlich folgende Codes aus der Codeliste UNTDID 1001 übermittelt werden: 326 (Partial invoice), 380 (Commercial invoice), 384 (Corrected invoice), 389 (Self-billed invoice) und 381 (Credit note),875 (Partial construction invoice), 876 (Partial final construction invoice), 877 (Final construction invoice).</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="every $line                        in rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradePaymentTerms/ram:Description[1]/tokenize(. , '(\r?\n)')[starts-with( normalize-space(.) , '#')]                        satisfies matches ( normalize-space ($line), $XR-SKONTO-REGEX ) and                     matches( rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradePaymentTerms/ram:Description[1]/tokenize(. ,  '#.+#')[last()], '^\s*\n' )"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="every $line in rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradePaymentTerms/ram:Description[1]/tokenize(. , '(\r?\n)')[starts-with( normalize-space(.) , '#')] satisfies matches ( normalize-space ($line), $XR-SKONTO-REGEX ) and matches( rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradePaymentTerms/ram:Description[1]/tokenize(. , '#.+#')[last()], '^\s*\n' )">
               <xsl:attribute name="id">BR-DE-18</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>[BR-DE-18] Skonto Zeilen in <xsl:text/>
                  <xsl:value-of select="name(.)"/>
                  <xsl:text/> muessen diesem regulärem Ausdruck entsprechen: <xsl:text/>
                  <xsl:value-of select="$XR-SKONTO-REGEX"/>
                  <xsl:text/>. Die Informationen zur Gewährung von Skonto müssen wie folgt im Element "Payment terms" (BT-20) übermittelt werden: Anzugeben ist im ersten Segment "SKONTO", im zweiten "TAGE=n", im dritten "PROZENT=n". Prozentzahlen sind ohne Vorzeichen sowie mit Punkt getrennt von zwei Nachkommastellen anzugeben. Liegt dem zu berechnenden Betrag nicht BT-115, "fälliger Betrag" zugrunde, sondern nur ein Teil des fälligen Betrags der Rechnung, ist der Grundwert zur Berechnung von Skonto als viertes Segment "BASISBETRAG=n" gemäß dem semantischen Datentypen Amount anzugeben. Jeder Eintrag beginnt mit einer #, die Segmente sind mit einer # getrennt und eine Zeile schließt mit einer # ab. Am Ende einer vollständigen Skontoangabe muss ein XML-konformer Zeilenumbruch folgen. Alle Angaben zur Gewährung von Skonto müssen in Großbuchstaben gemacht werden. Zusätzliches Whitespace (Leerzeichen, Tabulatoren oder Zeilenumbrüche) ist nicht zulässig. Andere Zeichen oder Texte als in den oberen Vorgaben genannt sind nicht zulässig.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="count(//ram:AdditionalReferencedDocument) = count(//ram:AdditionalReferencedDocument[not(./ram:AttachmentBinaryObject/@filename = preceding-sibling::ram:AdditionalReferencedDocument/ram:AttachmentBinaryObject/@filename)])"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="count(//ram:AdditionalReferencedDocument) = count(//ram:AdditionalReferencedDocument[not(./ram:AttachmentBinaryObject/@filename = preceding-sibling::ram:AdditionalReferencedDocument/ram:AttachmentBinaryObject/@filename)])">
               <xsl:attribute name="id">BR-DE-22</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>[BR-DE-22] Not all filename attributes of the embeddedDocumentBinaryObject elements are unique</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="not(normalize-space(rsm:ExchangedDocument/ram:TypeCode) = '384') or                     (rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:InvoiceReferencedDocument)"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="not(normalize-space(rsm:ExchangedDocument/ram:TypeCode) = '384') or (rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:InvoiceReferencedDocument)">
               <xsl:attribute name="id">BR-DE-26</xsl:attribute>
               <xsl:attribute name="flag">warning</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>[BR-DE-26] Wenn im Element Invoice type code (BT-3) der Code 384 (Corrected invoice) übergeben wird, soll PRECEDING INVOICE REFERENCE BG-3 mind. einmal vorhanden sein.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M30"/>
   </xsl:template>
   <!--RULE -->
   <xsl:template match="/rsm:CrossIndustryInvoice/rsm:ExchangedDocumentContext"
                 priority="1012"
                 mode="M30">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                       context="/rsm:CrossIndustryInvoice/rsm:ExchangedDocumentContext"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="ram:GuidelineSpecifiedDocumentContextParameter/ram:ID = $XR-CIUS-ID or                     ram:GuidelineSpecifiedDocumentContextParameter/ram:ID = $XR-EXTENSION-ID or                     ram:GuidelineSpecifiedDocumentContextParameter/ram:ID = $XR-CVD-ID"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="ram:GuidelineSpecifiedDocumentContextParameter/ram:ID = $XR-CIUS-ID or ram:GuidelineSpecifiedDocumentContextParameter/ram:ID = $XR-EXTENSION-ID or ram:GuidelineSpecifiedDocumentContextParameter/ram:ID = $XR-CVD-ID">
               <xsl:attribute name="id">BR-DE-21</xsl:attribute>
               <xsl:attribute name="flag">warning</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>[BR-DE-21] Das Element "Specification identifier" (BT-24) soll syntaktisch der Kennung des Standards XRechnung entsprechen.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M30"/>
   </xsl:template>
   <!--RULE -->
   <xsl:template match="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty"
                 priority="1011"
                 mode="M30">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                       context="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="ram:DefinedTradeContact"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl" test="ram:DefinedTradeContact">
               <xsl:attribute name="id">BR-DE-2</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>[BR-DE-2] Die Gruppe "SELLER CONTACT" (BG-6) muss übermittelt werden.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M30"/>
   </xsl:template>
   <!--RULE -->
   <xsl:template match="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:PostalTradeAddress"
                 priority="1010"
                 mode="M30">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                       context="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:PostalTradeAddress"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="ram:CityName[boolean(normalize-space(.))]"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="ram:CityName[boolean(normalize-space(.))]">
               <xsl:attribute name="id">BR-DE-3</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>[BR-DE-3] Das Element "Seller city" (BT-37) muss übermittelt werden.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="ram:PostcodeCode[boolean(normalize-space(.))]"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="ram:PostcodeCode[boolean(normalize-space(.))]">
               <xsl:attribute name="id">BR-DE-4</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>[BR-DE-4] Das Element "Seller post code" (BT-38) muss übermittelt werden.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M30"/>
   </xsl:template>
   <!--RULE -->
   <xsl:template match="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:DefinedTradeContact"
                 priority="1009"
                 mode="M30">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                       context="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:SellerTradeParty/ram:DefinedTradeContact"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="(ram:PersonName,ram:DepartmentName)[boolean(normalize-space(.))]"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="(ram:PersonName,ram:DepartmentName)[boolean(normalize-space(.))]">
               <xsl:attribute name="id">BR-DE-5</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>[BR-DE-5] Das Element "Seller contact point" (BT-41) muss übermittelt werden.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="ram:TelephoneUniversalCommunication/ram:CompleteNumber[boolean(normalize-space(.))]"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="ram:TelephoneUniversalCommunication/ram:CompleteNumber[boolean(normalize-space(.))]">
               <xsl:attribute name="id">BR-DE-6</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>[BR-DE-6] Das Element "Seller contact telephone number" (BT-42) muss übermittelt werden.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="ram:EmailURIUniversalCommunication/ram:URIID[boolean(normalize-space(.))]"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="ram:EmailURIUniversalCommunication/ram:URIID[boolean(normalize-space(.))]">
               <xsl:attribute name="id">BR-DE-7</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>[BR-DE-7] Das Element "Seller contact email address" (BT-43) muss übermittelt werden.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="matches(normalize-space(ram:TelephoneUniversalCommunication/ram:CompleteNumber), $XR-TELEPHONE-REGEX)"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="matches(normalize-space(ram:TelephoneUniversalCommunication/ram:CompleteNumber), $XR-TELEPHONE-REGEX)">
               <xsl:attribute name="id">BR-DE-27</xsl:attribute>
               <xsl:attribute name="flag">warning</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>[BR-DE-27] In BT-42 sollen mindestens drei Ziffern enthalten sein.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="matches(normalize-space(ram:EmailURIUniversalCommunication/ram:URIID), $XR-EMAIL-REGEX)"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="matches(normalize-space(ram:EmailURIUniversalCommunication/ram:URIID), $XR-EMAIL-REGEX)">
               <xsl:attribute name="id">BR-DE-28</xsl:attribute>
               <xsl:attribute name="flag">warning</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>[BR-DE-28] In BT-43 soll genau ein @-Zeichen enthalten sein, welches nicht von einem Leerzeichen, einem Punkt, aber mindestens zwei Zeichen auf beiden Seiten flankiert werden soll. Ein Punkt sollte nicht am Anfang oder am Ende stehen.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M30"/>
   </xsl:template>
   <!--RULE -->
   <xsl:template match="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:BuyerTradeParty/ram:PostalTradeAddress"
                 priority="1008"
                 mode="M30">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                       context="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:BuyerTradeParty/ram:PostalTradeAddress"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="ram:CityName[boolean(normalize-space(.))]"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="ram:CityName[boolean(normalize-space(.))]">
               <xsl:attribute name="id">BR-DE-8</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>[BR-DE-8] Das Element "Buyer city" (BT-52) muss übermittelt werden.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="ram:PostcodeCode[boolean(normalize-space(.))]"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="ram:PostcodeCode[boolean(normalize-space(.))]">
               <xsl:attribute name="id">BR-DE-9</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>[BR-DE-9] Das Element "Buyer post code" (BT-53) muss übermittelt werden.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M30"/>
   </xsl:template>
   <!--RULE -->
   <xsl:template match="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:AdditionalReferencedDocument[ram:TypeCode = '916']"
                 priority="1007"
                 mode="M30">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                       context="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement/ram:AdditionalReferencedDocument[ram:TypeCode = '916']"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="not(exists(ram:URIID)) or (matches(ram:URIID, $XR-URL-REGEX))"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="not(exists(ram:URIID)) or (matches(ram:URIID, $XR-URL-REGEX))">
               <xsl:attribute name="id">BR-TMP-2</xsl:attribute>
               <xsl:attribute name="flag">warning</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>[BR-TMP-2] BT-124 "External document location" muss eine absolute URL mit gültigem Schema enthalten.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M30"/>
   </xsl:template>
   <!--RULE -->
   <xsl:template match="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeDelivery/ram:ShipToTradeParty/ram:PostalTradeAddress"
                 priority="1006"
                 mode="M30">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                       context="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeDelivery/ram:ShipToTradeParty/ram:PostalTradeAddress"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="ram:CityName[boolean(normalize-space(.))]"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="ram:CityName[boolean(normalize-space(.))]">
               <xsl:attribute name="id">BR-DE-10</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>[BR-DE-10] Das Element "Deliver to city" (BT-77) muss übermittelt werden, wenn die Gruppe "DELIVER TO ADDRESS" (BG-15) übermittelt wird.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="ram:PostcodeCode[boolean(normalize-space(.))]"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="ram:PostcodeCode[boolean(normalize-space(.))]">
               <xsl:attribute name="id">BR-DE-11</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>[BR-DE-11] Das Element "Deliver to post code" (BT-78) muss übermittelt werden, wenn die Gruppe "DELIVER TO ADDRESS" (BG-15) übermittelt wird.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M30"/>
   </xsl:template>
   <!--RULE -->
   <xsl:template match="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementPaymentMeans[normalize-space(ram:TypeCode) = ('30','58')]"
                 priority="1005"
                 mode="M30">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                       context="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementPaymentMeans[normalize-space(ram:TypeCode) = ('30','58')]"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="not(normalize-space(ram:TypeCode) = '58') or                     matches(normalize-space(replace(ram:PayeePartyCreditorFinancialAccount/ram:IBANID, '([ \n\r\t\s])', '')), '^[A-Z]{2}[0-9]{2}[a-zA-Z0-9]{0,30}$') and                     xs:integer(string-join(for $cp in string-to-codepoints(concat(substring(normalize-space(replace(ram:PayeePartyCreditorFinancialAccount/ram:IBANID, '([ \n\r\t\s])', '')),5),upper-case(substring(normalize-space(replace(ram:PayeePartyCreditorFinancialAccount/ram:IBANID, '([ \n\r\t\s])', '')),1,2)),substring(normalize-space(replace(ram:PayeePartyCreditorFinancialAccount/ram:IBANID, '([ \n\r\t\s])', '')),3,2))) return  (if($cp &gt; 64) then string($cp - 55) else  string($cp - 48)),'')) mod 97 = 1"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="not(normalize-space(ram:TypeCode) = '58') or matches(normalize-space(replace(ram:PayeePartyCreditorFinancialAccount/ram:IBANID, '([ \n\r\t\s])', '')), '^[A-Z]{2}[0-9]{2}[a-zA-Z0-9]{0,30}$') and xs:integer(string-join(for $cp in string-to-codepoints(concat(substring(normalize-space(replace(ram:PayeePartyCreditorFinancialAccount/ram:IBANID, '([ \n\r\t\s])', '')),5),upper-case(substring(normalize-space(replace(ram:PayeePartyCreditorFinancialAccount/ram:IBANID, '([ \n\r\t\s])', '')),1,2)),substring(normalize-space(replace(ram:PayeePartyCreditorFinancialAccount/ram:IBANID, '([ \n\r\t\s])', '')),3,2))) return (if($cp &gt; 64) then string($cp - 55) else string($cp - 48)),'')) mod 97 = 1">
               <xsl:attribute name="id">BR-DE-19</xsl:attribute>
               <xsl:attribute name="flag">warning</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>[BR-DE-19] "Payment account identifier" (BT-84) soll eine korrekte IBAN enthalten, wenn in "Payment means type code" (BT-81) mit dem Code 58 SEPA als Zahlungsmittel gefordert wird.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="ram:PayeePartyCreditorFinancialAccount"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="ram:PayeePartyCreditorFinancialAccount">
               <xsl:attribute name="id">BR-DE-23-a</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>[BR-DE-23-a] Wenn BT-81 "Payment means type code" einen Schlüssel für Überweisungen enthält (30, 58), muss BG-17 "CREDIT TRANSFER" übermittelt werden.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="not(ram:ApplicableTradeSettlementFinancialCard) and                     not(/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradePaymentTerms/ram:DirectDebitMandateID or                         /rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:CreditorReferenceID or                         ram:PayerPartyDebtorFinancialAccount/ram:IBANID)"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="not(ram:ApplicableTradeSettlementFinancialCard) and not(/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradePaymentTerms/ram:DirectDebitMandateID or /rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:CreditorReferenceID or ram:PayerPartyDebtorFinancialAccount/ram:IBANID)">
               <xsl:attribute name="id">BR-DE-23-b</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>[BR-DE-23-b] Wenn BT-81 "Payment means type code" einen Schlüssel für Überweisungen enthält (30, 58), dürfen BG-18 und BG-19 nicht übermittelt werden.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M30"/>
   </xsl:template>
   <!--RULE -->
   <xsl:template match="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementPaymentMeans[normalize-space(ram:TypeCode) = ('48','54','55')]"
                 priority="1004"
                 mode="M30">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                       context="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementPaymentMeans[normalize-space(ram:TypeCode) = ('48','54','55')]"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="ram:ApplicableTradeSettlementFinancialCard"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="ram:ApplicableTradeSettlementFinancialCard">
               <xsl:attribute name="id">BR-DE-24-a</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>[BR-DE-24-a] Wenn BT-81 "Payment means type code" einen Schlüssel für Kartenzahlungen enthält (48, 54, 55), muss genau BG-18 "PAYMENT CARD INFORMATION" übermittelt werden.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="not(ram:PayeePartyCreditorFinancialAccount) and                     not(/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradePaymentTerms/ram:DirectDebitMandateID or                         /rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:CreditorReferenceID or                         ram:PayerPartyDebtorFinancialAccount/ram:IBANID)"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="not(ram:PayeePartyCreditorFinancialAccount) and not(/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradePaymentTerms/ram:DirectDebitMandateID or /rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:CreditorReferenceID or ram:PayerPartyDebtorFinancialAccount/ram:IBANID)">
               <xsl:attribute name="id">BR-DE-24-b</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>[BR-DE-24-b] Wenn BT-81 "Payment means type code" einen Schlüssel für Kartenzahlungen enthält (48, 54, 55), dürfen BG-17 und BG-19 nicht übermittelt werden.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M30"/>
   </xsl:template>
   <!--RULE -->
   <xsl:template match="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementPaymentMeans[normalize-space(ram:TypeCode) = '59']"
                 priority="1003"
                 mode="M30">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                       context="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradeSettlementPaymentMeans[normalize-space(ram:TypeCode) = '59']"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="not(normalize-space(ram:TypeCode) = '59') or                     matches(normalize-space(replace(ram:PayerPartyDebtorFinancialAccount/ram:IBANID, '([ \n\r\t\s])', '')), '^[A-Z]{2}[0-9]{2}[a-zA-Z0-9]{0,30}$') and                     xs:decimal(string-join(for $cp in string-to-codepoints(concat(substring(normalize-space(replace(ram:PayerPartyDebtorFinancialAccount/ram:IBANID, '([ \n\r\t\s])', '')),5),upper-case(substring(normalize-space(replace(ram:PayerPartyDebtorFinancialAccount/ram:IBANID, '([ \n\r\t\s])', '')),1,2)),substring(normalize-space(replace(ram:PayerPartyDebtorFinancialAccount/ram:IBANID, '([ \n\r\t\s])', '')),3,2))) return  (if($cp &gt; 64) then string($cp - 55) else  string($cp - 48)),'')) mod 97 = 1"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="not(normalize-space(ram:TypeCode) = '59') or matches(normalize-space(replace(ram:PayerPartyDebtorFinancialAccount/ram:IBANID, '([ \n\r\t\s])', '')), '^[A-Z]{2}[0-9]{2}[a-zA-Z0-9]{0,30}$') and xs:decimal(string-join(for $cp in string-to-codepoints(concat(substring(normalize-space(replace(ram:PayerPartyDebtorFinancialAccount/ram:IBANID, '([ \n\r\t\s])', '')),5),upper-case(substring(normalize-space(replace(ram:PayerPartyDebtorFinancialAccount/ram:IBANID, '([ \n\r\t\s])', '')),1,2)),substring(normalize-space(replace(ram:PayerPartyDebtorFinancialAccount/ram:IBANID, '([ \n\r\t\s])', '')),3,2))) return (if($cp &gt; 64) then string($cp - 55) else string($cp - 48)),'')) mod 97 = 1">
               <xsl:attribute name="id">BR-DE-20</xsl:attribute>
               <xsl:attribute name="flag">warning</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>[BR-DE-20] "Debited account identifier" (BT-91) soll eine korrekte IBAN enthalten, wenn in "Payment means type code" (BT-81) mit dem Code 59 SEPA als Zahlungsmittel gefordert wird.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradePaymentTerms/ram:DirectDebitMandateID or                     /rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:CreditorReferenceID or                     ram:PayerPartyDebtorFinancialAccount/ram:IBANID"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:SpecifiedTradePaymentTerms/ram:DirectDebitMandateID or /rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:CreditorReferenceID or ram:PayerPartyDebtorFinancialAccount/ram:IBANID">
               <xsl:attribute name="id">BR-DE-25-a</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>[BR-DE-25-a] Wenn BT-81 "Payment means type code" einen Schlüssel für Lastschriften enthält (59), muss genau BG-19 "DIRECT DEBIT" übermittelt werden.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="not(ram:PayeePartyCreditorFinancialAccount) and                     not(ram:ApplicableTradeSettlementFinancialCard)"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="not(ram:PayeePartyCreditorFinancialAccount) and not(ram:ApplicableTradeSettlementFinancialCard)">
               <xsl:attribute name="id">BR-DE-25-b</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>[BR-DE-25-b] Wenn BT-81 "Payment means type code" einen Schlüssel für Lastschriften enthält (59), dürfen BG-17 und BG-18 nicht übermittelt werden.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M30"/>
   </xsl:template>
   <!--RULE -->
   <xsl:template match="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax"
                 priority="1002"
                 mode="M30">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                       context="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeSettlement/ram:ApplicableTradeTax"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="ram:RateApplicablePercent[boolean(normalize-space(.))]"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="ram:RateApplicablePercent[boolean(normalize-space(.))]">
               <xsl:attribute name="id">BR-DE-14</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>[BR-DE-14] Das Element "VAT category rate" (BT-119) muss übermittelt werden.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M30"/>
   </xsl:template>
   <!--RULE -->
   <xsl:template match="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction"
                 priority="1001"
                 mode="M30">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                       context="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="         ram:ApplicableHeaderTradeDelivery/ram:ActualDeliverySupplyChainEvent/ram:OccurrenceDateTime         or ram:ApplicableHeaderTradeSettlement/ram:BillingSpecifiedPeriod         or (every $line in ram:IncludedSupplyChainTradeLineItem             satisfies $line/ram:SpecifiedLineTradeSettlement/ram:BillingSpecifiedPeriod)"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="ram:ApplicableHeaderTradeDelivery/ram:ActualDeliverySupplyChainEvent/ram:OccurrenceDateTime or ram:ApplicableHeaderTradeSettlement/ram:BillingSpecifiedPeriod or (every $line in ram:IncludedSupplyChainTradeLineItem satisfies $line/ram:SpecifiedLineTradeSettlement/ram:BillingSpecifiedPeriod)">
               <xsl:attribute name="id">BR-DE-TMP-32</xsl:attribute>
               <xsl:attribute name="flag">information</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>
        [BR-DE-TMP-32] Eine Rechnung sollte zur Angabe des Liefer-/Leistungsdatums entweder BT-72 "Actual delivery date", BG-14 "Invoicing period" oder in jeder Rechnungsposition BG-26 "Invoice line period" enthalten.
      </svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M30"/>
   </xsl:template>
   <!--RULE -->
   <xsl:template match="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedLineTradeAgreement"
                 priority="1000"
                 mode="M30">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                       context="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedLineTradeAgreement"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="not(./ram:NetPriceProductTradePrice/ram:BasisQuantity and ./ram:GrossPriceProductTradePrice/ram:BasisQuantity) or (./ram:NetPriceProductTradePrice/ram:BasisQuantity = ./ram:GrossPriceProductTradePrice/ram:BasisQuantity and ./ram:NetPriceProductTradePrice/ram:BasisQuantity/@unitCode = ./ram:GrossPriceProductTradePrice/ram:BasisQuantity/@unitCode)"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="not(./ram:NetPriceProductTradePrice/ram:BasisQuantity and ./ram:GrossPriceProductTradePrice/ram:BasisQuantity) or (./ram:NetPriceProductTradePrice/ram:BasisQuantity = ./ram:GrossPriceProductTradePrice/ram:BasisQuantity and ./ram:NetPriceProductTradePrice/ram:BasisQuantity/@unitCode = ./ram:GrossPriceProductTradePrice/ram:BasisQuantity/@unitCode)">
               <xsl:attribute name="id">BR-TMP-3</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>[BR-TMP-3] If both elements to which BT-149 and BT-150 can be mapped are present, both must be equal.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M30"/>
   </xsl:template>
   <xsl:template match="text()" priority="-1" mode="M30"/>
   <xsl:template match="@*|node()" priority="-2" mode="M30">
      <xsl:apply-templates select="*" mode="M30"/>
   </xsl:template>
   <!--PATTERN cii-extension-pattern-->
   <xsl:variable name="isExtension"
                 select="exists(/rsm:CrossIndustryInvoice/rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID[text() = concat( 'urn:cen.eu:en16931:2017#compliant#urn:xeinkauf.de:kosit:xrechnung_', $XR-MAJOR-MINOR-VERSION ,'#conformant#urn:xeinkauf.de:kosit:extension:xrechnung_', $XR-MAJOR-MINOR-VERSION) ] )"/>
   <!--RULE -->
   <xsl:template match="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem/ram:AssociatedDocumentLineDocument[$isExtension]"
                 priority="1006"
                 mode="M31">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                       context="/rsm:CrossIndustryInvoice/rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem/ram:AssociatedDocumentLineDocument[$isExtension]"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="not(exists(//ram:ParentLineID))"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="not(exists(//ram:ParentLineID))">
               <xsl:attribute name="id">BR-DEX-15</xsl:attribute>
               <xsl:attribute name="flag">warning</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>
              [BR-DEX-15] This CII file might use the concept of Sub Invoice Lines. However XRechnung does not support this.
          </svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M31"/>
   </xsl:template>
   <!--RULE -->
   <xsl:template match="//ram:GlobalID[@schemeID and $isExtension][not(ancestor::ram:SpecifiedTradeProduct) and not(ancestor::ram:ShipToTradeParty)]"
                 priority="1005"
                 mode="M31">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                       context="//ram:GlobalID[@schemeID and $isExtension][not(ancestor::ram:SpecifiedTradeProduct) and not(ancestor::ram:ShipToTradeParty)]"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="((not(contains(normalize-space(@schemeID), ' ')) and contains($ISO-6523-ICD-EXT-CODES, concat(' ', normalize-space(@schemeID), ' '))))"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="((not(contains(normalize-space(@schemeID), ' ')) and contains($ISO-6523-ICD-EXT-CODES, concat(' ', normalize-space(@schemeID), ' '))))">
               <xsl:attribute name="id">BR-DEX-04</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>[BR-DEX-04] Any scheme identifier in <xsl:text/>
                  <xsl:value-of select="name(.)"/>
                  <xsl:text/> MUST be coded using one of the ISO 6523 ICD list. </svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M31"/>
   </xsl:template>
   <!--RULE -->
   <xsl:template match="ram:ID[@schemeID and $isExtension][not(ancestor::ram:SpecifiedTaxRegistration)]"
                 priority="1004"
                 mode="M31">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                       context="ram:ID[@schemeID and $isExtension][not(ancestor::ram:SpecifiedTaxRegistration)]"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="((not(contains(normalize-space(@schemeID), ' ')) and contains($ISO-6523-ICD-EXT-CODES, concat(' ', normalize-space(@schemeID), ' '))))"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="((not(contains(normalize-space(@schemeID), ' ')) and contains($ISO-6523-ICD-EXT-CODES, concat(' ', normalize-space(@schemeID), ' '))))">
               <xsl:attribute name="id">BR-DEX-05</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>[BR-DEX-05] Any scheme identifier in <xsl:text/>
                  <xsl:value-of select="name(.)"/>
                  <xsl:text/> MUST be coded using one of the ISO 6523 ICD list. </svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M31"/>
   </xsl:template>
   <!--RULE -->
   <xsl:template match="ram:SpecifiedTradeProduct/ram:GlobalID[@schemeID and $isExtension]"
                 priority="1003"
                 mode="M31">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                       context="ram:SpecifiedTradeProduct/ram:GlobalID[@schemeID and $isExtension]"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="((not(contains(normalize-space(@schemeID), ' ')) and contains($ISO-6523-ICD-EXT-CODES, concat(' ', normalize-space(@schemeID), ' '))))"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="((not(contains(normalize-space(@schemeID), ' ')) and contains($ISO-6523-ICD-EXT-CODES, concat(' ', normalize-space(@schemeID), ' '))))">
               <xsl:attribute name="id">BR-DEX-06</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>[BR-DEX-06] Any scheme identifier in <xsl:text/>
                  <xsl:value-of select="name(.)"/>
                  <xsl:text/> MUST be coded using one of the ISO 6523 ICD list. </svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M31"/>
   </xsl:template>
   <!--RULE -->
   <xsl:template match="ram:URIUniversalCommunication/ram:URIID[@schemeID and $isExtension]"
                 priority="1002"
                 mode="M31">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                       context="ram:URIUniversalCommunication/ram:URIID[@schemeID and $isExtension]"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="((not(contains(normalize-space(@schemeID), ' ')) and contains($CEF-EAS-EXT-CODES, concat(' ', normalize-space(@schemeID), ' '))))"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="((not(contains(normalize-space(@schemeID), ' ')) and contains($CEF-EAS-EXT-CODES, concat(' ', normalize-space(@schemeID), ' '))))">
               <xsl:attribute name="id">BR-DEX-07</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>[BR-DEX-07] Any scheme identifier for an Endpoint Identifier in <xsl:text/>
                  <xsl:value-of select="name(.)"/>
                  <xsl:text/> MUST belong to the CEF EAS code list. </svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M31"/>
   </xsl:template>
   <!--RULE -->
   <xsl:template match="ram:ApplicableHeaderTradeDelivery/ram:ShipToTradeParty/ram:GlobalID[@schemeID and $isExtension]"
                 priority="1001"
                 mode="M31">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                       context="ram:ApplicableHeaderTradeDelivery/ram:ShipToTradeParty/ram:GlobalID[@schemeID and $isExtension]"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="((not(contains(normalize-space(@schemeID), ' ')) and contains($ISO-6523-ICD-EXT-CODES, concat(' ', normalize-space(@schemeID), ' '))))"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="((not(contains(normalize-space(@schemeID), ' ')) and contains($ISO-6523-ICD-EXT-CODES, concat(' ', normalize-space(@schemeID), ' '))))">
               <xsl:attribute name="id">BR-DEX-08</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>[BR-DEX-08] Any scheme identifier for a Delivery location identifier in <xsl:text/>
                  <xsl:value-of select="name(.)"/>
                  <xsl:text/> MUST be coded using one of the ISO 6523 ICD list. </svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M31"/>
   </xsl:template>
   <!--RULE -->
   <xsl:template match="ram:AttachmentBinaryObject[$isExtension]"
                 priority="1000"
                 mode="M31">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                       context="ram:AttachmentBinaryObject[$isExtension]"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test=".[@mimeCode = 'application/pdf' or               @mimeCode = 'image/png' or               @mimeCode = 'image/jpeg' or               @mimeCode = 'text/csv' or               @mimeCode = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' or               @mimeCode = 'application/vnd.oasis.opendocument.spreadsheet' or               @mimeCode = 'application/xml']"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test=".[@mimeCode = 'application/pdf' or @mimeCode = 'image/png' or @mimeCode = 'image/jpeg' or @mimeCode = 'text/csv' or @mimeCode = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' or @mimeCode = 'application/vnd.oasis.opendocument.spreadsheet' or @mimeCode = 'application/xml']">
               <xsl:attribute name="id">BR-DEX-01</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>[BR-DEX-01] Das Element <xsl:text/>
                  <xsl:value-of select="name(.)"/>
                  <xsl:text/> "Attached Document" (BT-125) benutzt einen nicht zulässigen MIME-Code: <xsl:text/>
                  <xsl:value-of select="@mimeCode"/>
                  <xsl:text/>. Im Falle einer Extension darf zusätzlich zu der Liste der mime codes (definiert in Abschnitt 8.2, "Binary Object") der MIME-Code application/xml genutzt werden.</svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M31"/>
   </xsl:template>
   <xsl:template match="text()" priority="-1" mode="M31"/>
   <xsl:template match="@*|node()" priority="-2" mode="M31">
      <xsl:apply-templates select="*" mode="M31"/>
   </xsl:template>
   <!--PATTERN cii-cvd-pattern-->
   <xsl:variable name="isCVD"
                 select="rsm:CrossIndustryInvoice/rsm:ExchangedDocumentContext/ram:GuidelineSpecifiedDocumentContextParameter/ram:ID/text() = $XR-CVD-ID"/>
   <!--RULE -->
   <xsl:template match="/rsm:CrossIndustryInvoice[$isCVD]/rsm:SupplyChainTradeTransaction"
                 priority="1004"
                 mode="M32">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                       context="/rsm:CrossIndustryInvoice[$isCVD]/rsm:SupplyChainTradeTransaction"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedTradeProduct[ram:DesignatedProductClassification/ram:ClassCode/@listID = 'CVD' and ram:ApplicableProductCharacteristic/ram:Description = 'cva']"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedTradeProduct[ram:DesignatedProductClassification/ram:ClassCode/@listID = 'CVD' and ram:ApplicableProductCharacteristic/ram:Description = 'cva']">
               <xsl:attribute name="id">BR-DE-CVD-03</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>
                [BR-DE-CVD-03] In einer Rechnung muss mindestens eine <xsl:text/>
                  <xsl:value-of select="name(.)"/>
                  <xsl:text/> INVOICE LINE (BG-25) enthalten sein, in der der Scheme identifier von <xsl:text/>
                  <xsl:value-of select="name(.)"/>
                  <xsl:text/> "Item classification identifier" (BT-158) den Wert 'CVD' und der <xsl:text/>
                  <xsl:value-of select="name(.)"/>
                  <xsl:text/> "Item attribute name" (BT-160) den Wert 'cva' enthält.
            </svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M32"/>
   </xsl:template>
   <!--RULE -->
   <xsl:template match="/rsm:CrossIndustryInvoice[$isCVD]/rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedTradeProduct"
                 priority="1003"
                 mode="M32">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                       context="/rsm:CrossIndustryInvoice[$isCVD]/rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedTradeProduct"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="not(ram:ApplicableProductCharacteristic/ram:Description = 'cva') or count(ram:DesignatedProductClassification/ram:ClassCode[@listID = 'CVD']) = 1"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="not(ram:ApplicableProductCharacteristic/ram:Description = 'cva') or count(ram:DesignatedProductClassification/ram:ClassCode[@listID = 'CVD']) = 1">
               <xsl:attribute name="id">BR-DE-CVD-06-b</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>
                [BR-DE-CVD-06-b] Wenn <xsl:text/>
                  <xsl:value-of select="name(.)"/>
                  <xsl:text/> "Item attribute name" (BT-160) mit dem Wert 'cva' angegeben ist, muss in derselben Rechnungszeile genau ein <xsl:text/>
                  <xsl:value-of select="name(.)"/>
                  <xsl:text/> "Item classification identifier" (BT-158) mit dem Scheme identifier 'CVD' vorhanden sein.
            </svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="not(ram:DesignatedProductClassification/ram:ClassCode/@listID = 'CVD') or count(ram:ApplicableProductCharacteristic[ram:Description = 'cva']) = 1"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="not(ram:DesignatedProductClassification/ram:ClassCode/@listID = 'CVD') or count(ram:ApplicableProductCharacteristic[ram:Description = 'cva']) = 1">
               <xsl:attribute name="id">BR-DE-CVD-06-a</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>
                [BR-DE-CVD-06-a] Wenn der Scheme identifier von <xsl:text/>
                  <xsl:value-of select="name(.)"/>
                  <xsl:text/> "Item classification identifier" (BT-158) mit dem Wert 'CVD' angegeben ist, muss in derselben Rechnungszeile genau ein <xsl:text/>
                  <xsl:value-of select="name(.)"/>
                  <xsl:text/> "Item attribute name" (BT-160) mit dem Wert 'cva' vorhanden sein.
            </svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M32"/>
   </xsl:template>
   <!--RULE -->
   <xsl:template match="/rsm:CrossIndustryInvoice[$isCVD]/rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedTradeProduct/ram:DesignatedProductClassification/ram:ClassCode"
                 priority="1002"
                 mode="M32">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                       context="/rsm:CrossIndustryInvoice[$isCVD]/rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedTradeProduct/ram:DesignatedProductClassification/ram:ClassCode"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="((not(contains(normalize-space(@listID), ' ')) and contains($UNTDID-7143-CVD-CODES, concat(' ', normalize-space(@listID), ' '))))"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="((not(contains(normalize-space(@listID), ' ')) and contains($UNTDID-7143-CVD-CODES, concat(' ', normalize-space(@listID), ' '))))">
               <xsl:attribute name="id">BR-TMP-CVD-01</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>
                [BR-TMP-CVD-01] Das Bildungsschema für <xsl:text/>
                  <xsl:value-of select="name(.)"/>
                  <xsl:text/> "Item classification identifier" (BT-158) ist aus der Codeliste UNTDID 7143 zu wählen.
            </svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="not(normalize-space(@listID) = 'CVD') or normalize-space(.) = $CVD-VEHICLE-CATEGORY"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="not(normalize-space(@listID) = 'CVD') or normalize-space(.) = $CVD-VEHICLE-CATEGORY">
               <xsl:attribute name="id">BR-DE-CVD-04</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>
                [BR-DE-CVD-04] Ein <xsl:text/>
                  <xsl:value-of select="name(.)"/>
                  <xsl:text/> "Item classification identifier" (BT-158) mit dem Scheme identifier 'CVD' muss einen Wert aus der Liste der zulässigen Fahrzeugkategorien enthalten.
            </svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M32"/>
   </xsl:template>
   <!--RULE -->
   <xsl:template match="/rsm:CrossIndustryInvoice[$isCVD]/rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedTradeProduct/ram:ApplicableProductCharacteristic[ram:Description = 'cva']"
                 priority="1001"
                 mode="M32">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                       context="/rsm:CrossIndustryInvoice[$isCVD]/rsm:SupplyChainTradeTransaction/ram:IncludedSupplyChainTradeLineItem/ram:SpecifiedTradeProduct/ram:ApplicableProductCharacteristic[ram:Description = 'cva']"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="normalize-space(ram:Value) = $CVA-CODES"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="normalize-space(ram:Value) = $CVA-CODES">
               <xsl:attribute name="id">BR-DE-CVD-05</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>
                [BR-DE-CVD-05] Wenn innerhalb von <xsl:text/>
                  <xsl:value-of select="name(.)"/>
                  <xsl:text/> ITEM ATTRIBUTES (BG-32) der <xsl:text/>
                  <xsl:value-of select="name(.)"/>
                  <xsl:text/> "Item attribute name" (BT-160) den Wert 'cva' hat, muss der <xsl:text/>
                  <xsl:value-of select="name(.)"/>
                  <xsl:text/> "Item attribute value" (BT-161) einen der zulässigen Werte enthalten.
            </svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M32"/>
   </xsl:template>
   <!--RULE -->
   <xsl:template match="/rsm:CrossIndustryInvoice[$isCVD]/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement"
                 priority="1000"
                 mode="M32">
      <svrl:fired-rule xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                       context="/rsm:CrossIndustryInvoice[$isCVD]/rsm:SupplyChainTradeTransaction/ram:ApplicableHeaderTradeAgreement"/>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="ram:ContractReferencedDocument/ram:IssuerAssignedID[boolean(normalize-space(.))]"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="ram:ContractReferencedDocument/ram:IssuerAssignedID[boolean(normalize-space(.))]">
               <xsl:attribute name="id">BR-DE-CVD-01</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>
                [BR-DE-CVD-01] Das Element <xsl:text/>
                  <xsl:value-of select="name(.)"/>
                  <xsl:text/> "Contract reference" (BT-12) muss übermittelt werden.
            </svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <!--ASSERT -->
      <xsl:choose>
         <xsl:when test="ram:AdditionalReferencedDocument[normalize-space(ram:TypeCode) = '50' and normalize-space(ram:IssuerAssignedID)]"/>
         <xsl:otherwise>
            <svrl:failed-assert xmlns:svrl="http://purl.oclc.org/dsdl/svrl"
                                test="ram:AdditionalReferencedDocument[normalize-space(ram:TypeCode) = '50' and normalize-space(ram:IssuerAssignedID)]">
               <xsl:attribute name="id">BR-DE-CVD-02</xsl:attribute>
               <xsl:attribute name="flag">fatal</xsl:attribute>
               <xsl:attribute name="location">
                  <xsl:apply-templates select="." mode="schematron-select-full-path"/>
               </xsl:attribute>
               <svrl:text>
                [BR-DE-CVD-02] Das Element <xsl:text/>
                  <xsl:value-of select="name(.)"/>
                  <xsl:text/> "Tender or lot reference" (BT-17) muss übermittelt werden.
            </svrl:text>
            </svrl:failed-assert>
         </xsl:otherwise>
      </xsl:choose>
      <xsl:apply-templates select="*" mode="M32"/>
   </xsl:template>
   <xsl:template match="text()" priority="-1" mode="M32"/>
   <xsl:template match="@*|node()" priority="-2" mode="M32">
      <xsl:apply-templates select="*" mode="M32"/>
   </xsl:template>
</xsl:stylesheet>