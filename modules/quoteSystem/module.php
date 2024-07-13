<?php
function getQuote($data) {
    global $dbconnection;

    $search = $data['commandargs'];
    $search = mysqli_real_escape_string($dbconnection, $search);
    
    //they are probably searching a specific id if search term is numeric
    if(is_numeric($search)) {
        $query = "SELECT * FROM quotes WHERE id = $search LIMIT 1";
    } elseif(strlen($search)>1) {
        $query = "SELECT * FROM quotes WHERE quote LIKE '%$search%' ORDER BY rand() LIMIT 1";
    } else {
        $query = "SELECT * FROM quotes ORDER BY rand() LIMIT 1";
    }

    if(strlen($search) == 0) {
        $notnick = $data['usernickname'];
        $query = "SELECT * FROM quotes WHERE submittedby NOT LIKE '%".$notnick."%' AND downvotes < 3 ORDER BY rand() LIMIT 1";
    }

    $result = mysqli_query($dbconnection, $query);
    if(mysqli_num_rows($result)>0) {
        while($row = mysqli_fetch_assoc($result)) {
            $id = $row['id'];
            $submittedby = $row['submittedby'];
            $quote = $row['quote'];
            
            //Replacement Array - Comment this out if you don't have this issue, but OMG I have tried everything
            //I can think of to deal with things like "â€‹" in the quotes. I had to resort to this and I hate it.
            $replacements = array(
                'â€™'   => "’",  // Right single quotation mark
                'â€œ'   => "“",  // Left double quotation mark
                'â€�'   => "”",  // Right double quotation mark
                'â€”'   => "—",  // Em dash
                'â€“'   => "–",  // En dash
                'â€¢'   => "•",  // Bullet
                'â€¦'   => "…",  // Ellipsis
                'â€‹'   => "",   // Zero-width space
                'â„¢'   => "™",  // Trademark
                'âˆ†'   => "∆",  // Increment
                'âˆ—'   => "∗",  // Asterisk operator
                'âˆš'   => "√",  // Square root
                'â‰¤'   => "≤",  // Less-than or equal to
                'â‰¥'   => "≥",  // Greater-than or equal to
                'âˆ‘'   => "∑",  // Summation
                'âˆ‚'   => "∂",  // Partial differential
                'âˆ‹'   => "∋",  // Such that
                'âˆ‰'   => "∈",  // Element of
                'âˆƒ'   => "∃",  // There exists
                'âˆ§'   => "∧",  // Logical and
                'âˆ¨'   => "∨",  // Logical or
                'â™ª'   => "♪",  // Music note
                'â†’'   => "→",  // Rightwards arrow
                'â†�'   => "←",  // Leftwards arrow
                'â†‘'   => "↑",  // Upwards arrow
                'â†“'   => "↓",  // Downwards arrow
                'â‡”'   => "⇔",  // Left right double arrow
                'â‡�'   => "⇐",  // Leftwards double arrow
                'â‡‘'   => "⇑",  // Upwards double arrow
                'â‡“'   => "⇓",  // Downwards double arrow
                'âœ“'   => "✓",  // Check mark
                'âœ•'   => "✕",  // Multiplication X
                'âœ”'   => "✔",  // Heavy check mark
                'âœ–'   => "✖",  // Heavy multiplication X
                'â˜†'   => "☆",  // White star
                'â˜‰'   => "☉",  // Sun
                'â˜ƒ'   => "☃",  // Snowman
                'â˜€'   => "☀",  // Black sun with rays
                'â™¥'   => "♥",  // Black heart suit
                'â™£'   => "♣",  // Black club suit
                'â™¦'   => "♦",  // Black diamond suit
                'â™ '   => "♠",  // Black spade suit
                'â˜‘'   => "☑",  // Ballot box with check
                'â—†'   => "◆",  // Black diamond
                'â—ˆ'   => "▌",  // Black rectangle
                'â—”'   => "▼",  // Black down-pointing triangle
                'â—˜'   => "◘",  // Inverse bullet
                'â—‹'   => "◦",  // White bullet
                'â—…'   => "▐",  // Black left rectangle
                'ðŸ‡¦ðŸ‡º' => "🇦🇺", // Australian flag
                'ðŸ‡§ðŸ‡·' => "🇧🇷", // Brazilian flag
                'ðŸ‡®ðŸ‡±' => "🇮🇱", // Israeli flag
                'ðŸ‡®ðŸ‡¹' => "🇮🇹", // Italian flag
                'ðŸ‡ªðŸ‡¸' => "🇪🇸", // Spanish flag
                'ðŸ‡¯ðŸ‡µ' => "🇯🇵", // Japanese flag
                'ðŸ‡ºðŸ‡¸' => "🇺🇸", // US flag
                'ðŸ‡¬ðŸ‡§' => "🇰🇧", // Kenyan flag
                'ðŸ‡®ðŸ‡²' => "🇮🇲", // Isle of Man flag
                'ðŸ‡®ðŸ‡¾' => "🇮🇾", // Isle of Wight flag
                'ðŸ‡«ðŸ‡·' => "🇫🇷", // French flag
                'ðŸ‡¦ðŸ‡¦' => "🇦🇦", // Ascension Island flag
                'ðŸ‡·ðŸ‡¸' => "🇷🇸", // Serbian flag
                'ðŸ‡©ðŸ‡µ' => "🇩🇰", // Danish flag
                'ðŸ‡±ðŸ‡ª' => "🇱🇪", // Lebanese flag
                'ðŸ‡ªðŸ‡³' => "🇪🇳", // Netherlands flag
                'ðŸ‡¨ðŸ‡·' => "🇨🇷", // Costa Rican flag
                'ðŸ‡µðŸ‡®' => "🇵🇮", // Philippine flag
                'ðŸ‡³ðŸ‡«' => "🇳🇧", // New Brunswick flag
                'ðŸ‡°ðŸ‡¸' => "🇰🇸", // Kansas flag
                'ðŸ‡¯ðŸ‡ª' => "🇰🇪", // Korean flag
                'ðŸ‡µðŸ‡ª' => "🇵🇪", // Peruvian flag
                'ðŸ‡§ðŸ‡¹' => "🇧🇹", // Bhutanese flag
                'ðŸ‡´ðŸ‡²' => "🇴🇲", // Omani flag
                'ðŸ‡­ðŸ‡·' => "🇱🇷", // Liberian flag
                'ðŸ‡¶ðŸ‡¼' => "🇶🇸", // Qatar flag
                'ðŸ‡¨ðŸ‡´' => "🇨🇴", // Colombian flag
            );
            $quote = str_replace(array_keys($replacements), array_values($replacements), $quote);
            $timestamp = $row['timestamp'];
            $upvotes = $row['upvotes'];
            $downvotes = $row['downvotes'];
            $message = "Quote #$id (U:$upvotes/D:$downvotes): $quote [submitted by $submittedby on $timestamp]";
        }
    } else {
        $message = "Unable to locate a quote matching the search term: $search";
    }
    sendPRIVMSG($data['location'],$message);  
    return true;
}

function addQuote($data) {
    global $dbconnection;
    global $timestamp;

    $quotetoadd = $data['commandargs'];
    $quotetoadd = mysqli_real_escape_string($dbconnection, $quotetoadd);
    $submittedby = $data['usernickname'];
    $query = "INSERT INTO quotes (submittedby,quote,timestamp,upvotes,downvotes) VALUES ('".$submittedby."','".$quotetoadd."','".$timestamp."',0,0)";
    if(mysqli_query($dbconnection, $query)) {
        $query = "SELECT id FROM quotes ORDER BY timestamp DESC LIMIT 1";
        $result = mysqli_query($dbconnection,$query);
        if(mysqli_num_rows($result) == 1) {
            while($row = mysqli_fetch_assoc($result)) {
                $id = $row['id'];
                sendPRIVMSG($data['location'],"Quote #$id added!");
            }
        }
    } else {
        sendPRIVMSG($data['location'],"Failed to add quote.");
    }
    $quotetoadd = "";
    $query = "";
    $result = "";
    $id = "";
    return true;
}

function upvoteQuote($data) {
    global $dbconnection;

    $quoteid = trim($data['commandargs']);
    if(is_numeric($quoteid)) {
        $quoteid = mysqli_real_escape_string($dbconnection, $quoteid);
        $query = "SELECT id,upvotes,downvotes,voted_hostnames FROM quotes WHERE id = $quoteid LIMIT 1";
    } else {
        $message = "Invalid quote ID.";
        sendPRIVMSG($data['location'],$message);
        return true;
    }
    $result = mysqli_query($dbconnection,$query);
    $query = "";
    if(mysqli_num_rows($result)>0) {
        //do the thing
        while($row = mysqli_fetch_assoc($result)) {
            $id = $row['id'];
            $upvotes = $row['upvotes'];
            $voted_hostnames = $row['voted_hostnames'];
            $votedhostnamesArray = unserialize($voted_hostnames);
            if(empty($votedhostnamesArray)) {
                $votedhostnamesArray = array();
            }
            if(is_array($votedhostnamesArray)) {
                if(in_array($data['userhostname'],$votedhostnamesArray)) {
                    $message = "You have already voted for quote #".$id."";
                    sendPRIVMSG($data['location'],$message);
                    return true;
                } else {
                    array_push($votedhostnamesArray,$data['userhostname']);
                    $newupvotes = $upvotes + 1;
                    $votedhostnames = serialize($votedhostnamesArray);
                    $query = "UPDATE quotes SET upvotes = $newupvotes, voted_hostnames = '".$votedhostnames."' WHERE id = $id LIMIT 1";
                }
            }
            
            if(mysqli_query($dbconnection,$query)) {
                $message = "".$data['usernickname']." - your upvote has been applied to quote #$id.";
            } else {
                $message = "Unable to apply vote. Please try again using !up [quote_id]";
            }
        }
    } else {
        $message = "Unable to apply vote. Please try again using !up [quote_id]";
    }
    sendPRIVMSG($data['location'],$message);
    return true;
}

function downvoteQuote($data) {
    global $dbconnection;

    $quoteid = trim($data['commandargs']);
    if(is_numeric($quoteid)) {
        $quoteid = mysqli_real_escape_string($dbconnection, $quoteid);
        $query = "SELECT id,upvotes,downvotes,voted_hostnames FROM quotes WHERE id = $quoteid LIMIT 1";
    } else {
        $message = "Invalid quote ID.";
        sendPRIVMSG($data['location'],$message);
        return true;
    }
    $result = mysqli_query($dbconnection,$query);
    $query = "";
    if(mysqli_num_rows($result)>0) {
        //do the thing
        while($row = mysqli_fetch_assoc($result)) {
            $id = $row['id'];
            $downvotes = $row['downvotes'];
            $voted_hostnames = $row['voted_hostnames'];
            $votedhostnamesArray = unserialize($voted_hostnames);
            if(empty($votedhostnamesArray)) {
                $votedhostnamesArray = array();
            }
            if(is_array($votedhostnamesArray)) {
                if(in_array($data['userhostname'],$votedhostnamesArray)) {
                    $message = "You have already voted for quote #".$id."";
                    sendPRIVMSG($data['location'],$message);
                    return true;
                } else {
                    array_push($votedhostnamesArray,$data['userhostname']);
                    $newdownvotes = $downvotes + 1;
                    $votedhostnames = serialize($votedhostnamesArray);
                    $query = "UPDATE quotes SET downvotes = $newdownvotes, voted_hostnames = '".$votedhostnames."' WHERE id = $id LIMIT 1";
                }
            }
            
            if(mysqli_query($dbconnection,$query)) {
                $message = "".$data['usernickname'].": your downvote has been applied to quote #$id.";
            } else {
                $message = "Unable to apply vote. Please try again using !down [quote_id]";
            }
        }
    } else {
        $message = "Unable to apply vote. Please try again using !down [quote_id]";
    }
    sendPRIVMSG($data['location'],$message);
    return true;
}

function getTopQuote($data) {
    global $dbconnection;

    $query = "SELECT id,submittedby,quote,timestamp,upvotes,downvotes FROM quotes ORDER BY upvotes DESC LIMIT 1";
    $result = mysqli_query($dbconnection,$query);
    if(mysqli_num_rows($result) == 1) {
        while($row = mysqli_fetch_assoc($result)) {
            $id = $row['id'];
            $upvotes = $row['upvotes'];
            $downvotes = $row['downvotes'];
            $submittedby = $row['submittedby'];
            $quote = $row['quote'];
            $timestamp = $row['timestamp'];
            $message = "Best Quote: #$id (U:".$upvotes."/D:".$downvotes.") - ".$quote."  [submitted by ".$submittedby." on ".$timestamp."]";
        }
    } else {
        $message = "Encountered an error polling for quote stats.";
    }
    sendPRIVMSG($data['location'],$message);
    return true;
}

function getBottomQuote($data) {
    global $dbconnection;

    $query = "SELECT id,submittedby,quote,timestamp,upvotes,downvotes FROM quotes ORDER BY downvotes ASC LIMIT 1";
    $result = mysqli_query($dbconnection,$query);
    if(mysqli_num_rows($result) == 1) {
        while($row = mysqli_fetch_assoc($result)) {
            $id = $row['id'];
            $upvotes = $row['upvotes'];
            $downvotes = $row['downvotes'];
            $submittedby = $row['submittedby'];
            $quote = $row['quote'];
            $timestamp = $row['timestamp'];
            $message = "Worst Quote: #$id (U:".$upvotes."/D:".$downvotes.") - ".$quote."  [submitted by ".$submittedby." on ".$timestamp."]";
        }
    } else {
        $message = "Encountered an error polling for quote stats.";
    }
    sendPRIVMSG($data['location'],$message);
    return true;
}

function getQuoteStats($data) {
    global $dbconnection; 

    $countquery = "SELECT count(*) AS total FROM quotes";
    $totalsubmittersquery = "SELECT count(distinct submittedby) AS users FROM quotes";
    $topsubmitter = "select submittedby, count(*) as total from quotes group by submittedby order by count(*) desc limit 1";
    $totalUpvotesquery = "SELECT SUM(upvotes) as upvotes FROM quotes";
    $totalDownvotesquery = "SELECT SUM(downvotes) as downvotes FROM quotes";
    $countresult = mysqli_query($dbconnection,$countquery);
    $totalsubmittersresult = mysqli_query($dbconnection,$totalsubmittersquery);
    $topresult = mysqli_query($dbconnection,$topsubmitter);
    $totalUpvotesresult = mysqli_query($dbconnection,$totalUpvotesquery);
    $totalDownvotesresult = mysqli_query($dbconnection,$totalDownvotesquery);
    if(mysqli_num_rows($countresult) == 1) {
        while($row = mysqli_fetch_assoc($countresult)) {
            $totalQuotes = $row['total'];
        }
        while($row = mysqli_fetch_assoc($totalsubmittersresult)) {
            $totalSubmitters = $row['users'];
        }
        while($row = mysqli_fetch_assoc($totalUpvotesresult)) {
            $totalUpvotes = $row['upvotes'];
        }
        while($row = mysqli_fetch_assoc($totalDownvotesresult)) {
            $totalDownvotes = $row['downvotes'];
            $totalDownvotes = str_replace("-","",$totalDownvotes);
        }
        while($row = mysqli_fetch_assoc($topresult)) {
            $submittedby = $row['submittedby'];
            $total = $row['total'];
        }
        $topPercentage = ( ($total / $totalQuotes) * 100 );
        $topPercentage = round($topPercentage,2);
        $message = "".$data['usernickname']." - There are ".$totalQuotes." total quotes in the database, submitted by ".$totalSubmitters." unique usernames. A total of ".$totalUpvotes." upvotes and ".$totalDownvotes." downvotes have been counted. The user ".$submittedby." has submitted the most quotes at ".$total." - a total of ".$topPercentage."% of all quotes!";
    }
    sendPRIVMSG($data['location'],$message);
    return true;
}

function getMyQuoteStats($data) {
    global $dbconnection;
    global $config;

    //EXPERIMENTAL BRIDGE SUPPORT
    if($config['bridge_enabled'] == true && stristr($data['usernickname'],$config['bridge_user_prefix'])) {
        $data['usernickname'] = trim(str_replace($config['bridge_user_prefix'],"",$data['usernickname']));
    }

    $totalcountquery = "SELECT count(*) AS totalcount FROM quotes";
    $mycountquery = "SELECT count(*) AS mytotal FROM quotes WHERE submittedby like '%".$data['usernickname']."%'";
    $votedcountquery = "SELECT count(*) AS votedtotal FROM quotes WHERE voted_hostnames LIKE '%".$data['userhostname']."%'";
    $mentionedcountquery = "SELECT count(*) AS mentionedtotal FROM quotes WHERE quote LIKE '%".$data['usernickname']."%'";
    $bestquotequery = "SELECT id AS bestquoteid FROM quotes WHERE submittedby LIKE '%".$data['usernickname']."%' ORDER BY upvotes DESC LIMIT 1";
    $worstquotequery = "SELECT id AS worstquoteid FROM quotes WHERE submittedby LIKE '%".$data['usernickname']."%' ORDER BY downvotes DESC LIMIT 1";
    $totalupvotesquery = "SELECT SUM(upvotes) AS totalupvotes FROM quotes WHERE submittedby LIKE '%".$data['usernickname']."%'";
    $totaldownvotesquery = "SELECT SUM(downvotes) AS totaldownvotes FROM quotes WHERE submittedby LIKE '%".$data['usernickname']."%'";
    $upvotecountquery = "SELECT SUM(upvotes) AS allupvotes FROM quotes";
    $downvotecountquery = "SELECT SUM(downvotes) AS alldownvotes FROM quotes";


    $totalcountresult = mysqli_query($dbconnection,$totalcountquery);
    $mycountresult = mysqli_query($dbconnection,$mycountquery);
    $votedcountresult = mysqli_query($dbconnection,$votedcountquery);
    $mentionedcountresult = mysqli_query($dbconnection,$mentionedcountquery);
    $bestquoteresult = mysqli_query($dbconnection,$bestquotequery);
    $worstquoteresult = mysqli_query($dbconnection,$worstquotequery);
    $totalupvotesresult = mysqli_query($dbconnection,$totalupvotesquery);
    $totaldownvotesresult = mysqli_query($dbconnection,$totaldownvotesquery);
    $upvotecountresult = mysqli_query($dbconnection,$upvotecountquery);
    $downvotecountresult = mysqli_query($dbconnection,$downvotecountquery);

    if(mysqli_num_rows($totalcountresult) == 1) {
        while($row = mysqli_fetch_assoc($totalcountresult)) {
            $totalCount = $row['totalcount'];
        }
        while($row = mysqli_fetch_assoc($mycountresult)) {
            $myTotalCount = $row['mytotal'];
        }
        while($row = mysqli_fetch_assoc($votedcountresult)) {
            $votedCount = $row['votedtotal'];
        }
        while($row = mysqli_fetch_assoc($mentionedcountresult)) {
            $mentionedCount = $row['mentionedtotal'];
        }
        while($row = mysqli_fetch_assoc($bestquoteresult)) {
            $bestQuote = $row['bestquoteid'];
        }
        while($row = mysqli_fetch_assoc($worstquoteresult)) {
            $worstQuote = $row['worstquoteid'];
        }
        while($row = mysqli_fetch_assoc($totalupvotesresult)) {
            $totalUpvotes = $row['totalupvotes'];
        }
        while($row = mysqli_fetch_assoc($totaldownvotesresult)) {
            $totalDownvotes = $row['totaldownvotes'];
        }
        while($row = mysqli_fetch_assoc($upvotecountresult)) {
            $upvoteCount = $row['allupvotes'];
        }
        while($row = mysqli_fetch_assoc($downvotecountresult)) {
            $downvoteCount = $row['alldownvotes'];
        }


        $myPercentage = ( ($myTotalCount / $totalCount) * 100 );
        $myPercentage = round($myPercentage,2);
        $upvotePercentage = ( ($totalUpvotes / $upvoteCount) * 100 );
        $upvotePercentage = round($upvotePercentage,2);
        $downvotePercentage = ( ($totalDownvotes / $downvoteCount) * 100);
        $downvotePercentage = round($downvotePercentage,2);

        if($myTotalCount == "0") {
            $message = "".$data['usernickname']." Quote Stats | You have not submitted any quotes yet!";
            if($mentionedCount == "0" || !is_numeric($mentionedCount)) {
                $message = "".$message." You also haven't been mentioned in any quotes.";
            } else {
                $message = "".$message." However, you have been mentioned in ".$mentionedCount." quotes.";
            }
            if($votedCount == "0" || !is_numeric($votedCount)) {
               $message = "".$message." You have not voted for any quotes, either!";
            } else {
               $message = "".$message." You have voted for ".$votedCount." quotes.";
            }
        } else {
            $message = "".$data['usernickname']." Quote Stats | Submitted: ".$myTotalCount." (".$myPercentage."% of all quotes). You have received ".$totalUpvotes." upvotes (".$upvotePercentage."%) and ".$totalDownvotes." downvotes (".$downvotePercentage."%). You've voted for ".$votedCount." quotes, and have been mentioned in ".$mentionedCount.". Your best quote is #".$bestQuote.", and your worst is #".$worstQuote.".";
        }
    }
    sendPRIVMSG($data['location'],$message);
    return true;
}