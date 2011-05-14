<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet version="1.0" xmlns:marc="http://www.loc.gov/MARC21/slim" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsl="http://www.w3.org/1999/XSL/Transform" exclude-result-prefixes="marc">
    <xsl:output method="text" indent="yes"/>
    <xsl:param name="format" select="'long'"/>

    <xsl:template match="/marc:record">
      <xsl:variable name="y008">
        <xsl:value-of select="substring(marc:controlfield[@tag=008],1,2)"/>
      </xsl:variable>
      <xsl:variable name="datestr">
        <xsl:choose>
          <xsl:when test="marc:controlfield[@tag=005]">
            <xsl:value-of select="substring(marc:controlfield[@tag=005],1,14)"/>
          </xsl:when>
          <xsl:when test="marc:controlfield[@tag=008]">
            <xsl:choose>
              <xsl:when test="number($y008) &gt; 68">
                <xsl:text>19</xsl:text>
              </xsl:when>
              <xsl:otherwise>
                <xsl:text>20</xsl:text>
              </xsl:otherwise>
            </xsl:choose>
            <xsl:value-of select="substring(marc:controlfield[@tag=008],1,6)"/>
            <xsl:text>000000</xsl:text>
          </xsl:when>
          <xsl:otherwise>
            <xsl:text>00000000000000</xsl:text>
          </xsl:otherwise>
        </xsl:choose>
      </xsl:variable>
      <xsl:choose>
        <xsl:when test="$format = 'short'">
            <xsl:value-of select="substring($datestr,1,4)"/>
            <xsl:text>-</xsl:text>
            <xsl:value-of select="substring($datestr,5,2)"/>
            <xsl:text>-</xsl:text>
            <xsl:value-of select="substring($datestr,7,2)"/>
        </xsl:when>
        <xsl:otherwise>
            <xsl:value-of select="substring($datestr,1,4)"/>
            <xsl:text>-</xsl:text>
            <xsl:value-of select="substring($datestr,5,2)"/>
            <xsl:text>-</xsl:text>
            <xsl:value-of select="substring($datestr,7,2)"/>
            <xsl:text>T</xsl:text>
            <xsl:value-of select="substring($datestr,9,2)"/>
            <xsl:text>:</xsl:text>
            <xsl:value-of select="substring($datestr,11,2)"/>
            <xsl:text>:</xsl:text>
            <xsl:value-of select="substring($datestr,13,2)"/>
            <xsl:text>Z</xsl:text>
        </xsl:otherwise>
      </xsl:choose>
    </xsl:template>

</xsl:stylesheet>
