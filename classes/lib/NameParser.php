<?php

// split full names into the following parts:
// - prefix / salutation  (Mr., Mrs., etc)
// - given name / first name
// - middle initials
// - surname / last name
// - suffix (II, Phd, Jr, etc)

class FullNameParser
{

    public function split_full_name($full_name) {
        $full_name = trim($full_name);
        // setup default values
        $lname = '';
        // split into words
        $unfiltered_name_parts = explode(" ",$full_name);
        // completely ignore any words in parentheses
        foreach ($unfiltered_name_parts as $word) {
            if (@$word[0] != "(")
                $name_parts[] = $word;
        }
        $num_words = sizeof($name_parts);

        // is the first word a title? (Mr. Mrs, etc)
        $salutation = $this->is_salutation($name_parts[0]);

        // set the range for the middle part of the name (trim prefixes & suffixes)
        $start = ($salutation) ? 1 : 0;

        //the first name
        $fname = $this->fix_case($name_parts[$start]);

        // concat the last name
        for ($i=$start+1; $i < $num_words; $i++) {
            $lname .= " ".$this->fix_case($name_parts[$i]);
        }


        // return the various parts in an array
        $name['fullname']   = $full_name;
        $name['salutation'] = $salutation;
        $name['fname'] = trim($fname);
        $name['lname'] = trim($lname);
        return $name;
    }

    // detect and format standard salutations
    // I'm only considering english honorifics for now & not words like
    public function is_salutation($word) {
        // ignore periods
        $word = str_replace('.','',strtolower($word));
        // returns normalized values
        if ($word == "mr" || $word == "master" || $word == "mister")
            return "Mr.";
        else if ($word == "mrs")
            return "Mrs.";
        else if ($word == "miss" || $word == "ms")
            return "Ms.";
        else if ($word == "dr")
            return "Dr.";
        else if ($word == "rev")
            return "Rev.";
        else if ($word == "fr")
            return "Fr.";
        else
            return false;
    }

    // detect mixed case words like "McDonald"
    // returns false if the string is all one case
    public function is_camel_case($word) {
        if (preg_match("|[A-Z]+|s", $word) && preg_match("|[a-z]+|s", $word))
            return true;
        return false;
    }

    // ucfirst words split by dashes or periods
    // ucfirst all upper/lower strings, but leave camelcase words alone
    public function fix_case($word) {
        // uppercase words split by dashes, like "Kimura-Fay"
        $word = $this->safe_ucfirst("-",$word);
        // uppercase words split by periods, like "J.P."
        $word = $this->safe_ucfirst(".",$word);
        return $word;
    }

    // helper public function for fix_case
    public function safe_ucfirst($seperator, $word) {
        // uppercase words split by the seperator (ex. dashes or periods)
        $parts = explode($seperator,$word);
        foreach ($parts as $word) {
            $words[] = ($this->is_camel_case($word)) ? $word : ucfirst(strtolower($word));
        }
        return implode($seperator,$words);
    }

}