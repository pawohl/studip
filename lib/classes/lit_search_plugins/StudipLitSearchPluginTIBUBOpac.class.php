<?php
# Lifter002: TODO
# Lifter007: TODO
# Lifter003: TODO
# Lifter010: TODO
// +---------------------------------------------------------------------------+
// This file is part of Stud.IP
// StudipLitSearchPluginRkgoe.class.php
// 
// 
// Copyright (c) 2003 Andr� Noack <noack@data-quest.de>
// +---------------------------------------------------------------------------+
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or any later version.
// +---------------------------------------------------------------------------+
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
// You should have received a copy of the GNU General Public License
// along with this program; if not, write to the Free Software
// Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
// +---------------------------------------------------------------------------+

require_once ("lib/classes/lit_search_plugins/StudipLitSearchPluginGvk.class.php");

/**
* Plugin for retrieval using Z39.50 
*
* 
*
* @access   public  
* @author   Andr� Noack <noack@data-quest.de>
* @package  
**/
class StudipLitSearchPluginTIBUBOpac extends StudipLitSearchPluginGvk{
    
    
    function StudipLitSearchPluginTIBUBOpac(){
        parent::StudipLitSearchPluginGvk();
        $this->description = "Technische Informationsbibliothek / Universit�tsbibliothek Hannover";
        $this->z_host = "z3950.gbv.de:20012/tib_opc";
        $this->z_record_encoding = 'utf-8';
        $this->z_profile = array('1016' => _("alle W�rter [ALL]"),
                     '1004' => _("Person, Autor [PER]"),                                         
                     '4' => _("Titelstichw�rter [TIT]"),
                     '5' => _("Stichw�rter Serie/Zeitschrift [SER]"),
                     '1005' => _("Stichw�rter K�rperschaft [KOR]"),                                      
                     '46' => _("Schlagw�rter [SWW]"),                                        
                     '54' => _("Signatur [SGN]"),                                        
                     '1007' => _("alle Nummern (ISBN, ISSN ...) [NUM]")
        ); /* '4' => _("Titelanf�nge [TAF]"),
           herausgenommen, da keine Unterscheidung anhand von Wort oder Phrase durchgef�hrt wird. 
           */
    }
}
?>
